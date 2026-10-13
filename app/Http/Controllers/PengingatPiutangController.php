<?php

namespace App\Http\Controllers;

use App\Models\AcosysConnection;
use App\Models\ReminderContact;
use App\Models\ReminderSetting;
use App\Models\ReminderTemplate;
use App\Models\WaDevice;
use App\Models\WaMessage;
use App\Services\AcosysConnector;
use App\Services\WaGatewayClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PengingatPiutangController extends Controller
{
    protected AcosysConnector $connector;
    protected WaGatewayClient $waClient;

    public function __construct(AcosysConnector $connector, WaGatewayClient $waClient)
    {
        $this->connector = $connector;
        $this->waClient = $waClient;
    }

    protected function getActiveAccount()
    {
        return Auth::user()?->getActiveAccount();
    }

    protected function getActiveConnection()
    {
        $account = $this->getActiveAccount();
        if (!$account) return null;

        $connId = session('active_connection_id');
        if ($connId) {
            $conn = $account->connections()->find($connId);
            if ($conn) return $conn;
        }

        return $account->connections()->first();
    }

    public function index(Request $request)
    {
        $account = $this->getActiveAccount();
        $connection = $this->getActiveConnection();

        if (!$connection) {
            return redirect()->route('settings.connections')
                ->with('info', 'Silakan tambahkan atau pilih koneksi database Acosys terlebih dahulu untuk menggunakan modul Pengingat Piutang.');
        }

        $filters = [
            'search' => $request->input('q', ''),
            'overdue_only' => $request->boolean('overdue_only'),
        ];

        $receivables = [];
        $connectionError = null;
        $company = ['name' => $connection->label];

        try {
            $receivables = $this->connector->getReceivables($connection, $filters);
            $company = $this->connector->getCompanyInfo($connection);
        } catch (Throwable $e) {
            $connectionError = "Gagal memuat data dari database Acosys: " . $e->getMessage();
        }

        $totalPiutang = array_sum(array_column($receivables, 'total_piutang'));
        $totalOverdue = array_sum(array_column($receivables, 'overdue_count'));
        $totalCustomers = count($receivables);

        $waDevices = $account->waDevices;
        $connectedDevice = $account->waDevices()->where('status', 'connected')->first();
        $templates = ReminderTemplate::where('account_id', $account->id)->get();

        return view('modules.pengingat_piutang.index', compact(
            'account',
            'connection',
            'receivables',
            'company',
            'filters',
            'totalPiutang',
            'totalOverdue',
            'totalCustomers',
            'connectionError',
            'waDevices',
            'connectedDevice',
            'templates'
        ));
    }

    /**
     * AJAX endpoint: Invoices detail per customer
     */
    public function getCustomerInvoices($customerId)
    {
        $connection = $this->getActiveConnection();
        if (!$connection) {
            return response()->json(['status' => false, 'message' => 'Koneksi database tidak aktif']);
        }

        try {
            $invoices = $this->connector->getCustomerInvoices($connection, $customerId);
            return response()->json([
                'status' => true,
                'customer_id' => $customerId,
                'invoices' => $invoices,
            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Save Phone Override or Opt-Out
     */
    public function saveContact(Request $request)
    {
        $connection = $this->getActiveConnection();
        if (!$connection) {
            return back()->with('error', 'Koneksi tidak ditemukan');
        }

        $customerId = $request->input('customer_id');
        $phoneOverride = $request->input('phone_override');
        $optOut = $request->boolean('opt_out');

        ReminderContact::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'customer_id' => $customerId,
            ],
            [
                'phone_override' => $phoneOverride,
                'opt_out' => $optOut,
            ]
        );

        return back()->with('success', "Kontak pelanggan {$customerId} berhasil disimpan.");
    }

    /**
     * Send instant single reminder via WA Gateway
     */
    /**
     * Helper to render dynamic template variables
     */
    protected function renderMessage(string $template, array $data): string
    {
        $replace = [
            '{nama_pelanggan}' => $data['customer_name'] ?? '',
            '{nama_toko}' => $data['company_name'] ?? '',
            '{total_piutang}' => $data['total_piutang'] ?? '',
            '{jumlah_nota}' => $data['unpaid_count'] ?? '1',
            '{tanggal_tempo}' => $data['due_date'] ?? '-',
            '{tanggal_sekarang}' => now()->format('d/m/Y H:i'),
        ];
        return str_replace(array_keys($replace), array_values($replace), $template);
    }

    /**
     * Send instant single reminder via WA Gateway
     */
    public function sendSingle(Request $request)
    {
        $account = $this->getActiveAccount();
        $connection = $this->getActiveConnection();

        $customerId = $request->input('customer_id');
        $customerName = $request->input('customer_name');
        $phone = $request->input('phone');
        $totalPiutang = $request->input('total_piutang');
        $unpaidCount = $request->input('unpaid_count', '1');
        $dueDate = $request->input('due_date', '-');
        $deviceId = $request->input('device_id');

        $device = $account->waDevices()->find($deviceId) ?: $account->waDevices()->where('status', 'connected')->first();

        if (!$device) {
            return back()->with('error', 'Tidak ada perangkat WhatsApp sender yang terhubung. Silakan hubungkan WhatsApp di Pengaturan.');
        }

        if (empty($phone) || strlen($phone) < 8) {
            return back()->with('error', "Nomor WhatsApp untuk pelanggan {$customerName} belum diisi atau tidak valid.");
        }

        // Fetch company name
        $company = $this->connector->getCompanyInfo($connection);
        $companyName = $company['name'] ?? 'Toko';

        // Fetch custom template or fallback to default
        $template = ReminderTemplate::where('account_id', $account->id)->where('type', 'customer')->first();
        $defaultTemplate = "Halo Bapak/Ibu *{nama_pelanggan}*,\n\nKami dari *{nama_toko}* menginformasikan bahwa terdapat tagihan yang belum terselesaikan sebesar *{total_piutang}* ({jumlah_nota} nota belum lunas).\n\nMohon untuk melakukan konfirmasi atau penyelesaian pembayaran. Terima kasih atas kerja samanya.";
        $templateBody = !empty($template?->body) ? $template->body : $defaultTemplate;

        // Render message body with dynamic tags
        $formattedAmount = 'Rp ' . number_format((float)$totalPiutang, 0, ',', '.');
        $body = $this->renderMessage($templateBody, [
            'customer_name' => $customerName,
            'company_name' => $companyName,
            'total_piutang' => $formattedAmount,
            'unpaid_count' => $unpaidCount,
            'due_date' => $dueDate,
        ]);

        // Send via WA Gateway
        $sendResult = $this->waClient->sendMessage($device->session_key, $phone, $body);

        $status = $sendResult['status'] ? 'sent' : 'failed';
        $error = $sendResult['status'] ? null : ($sendResult['message'] ?? 'Error');

        // Log message
        WaMessage::create([
            'account_id' => $account->id,
            'connection_id' => $connection->id,
            'device_id' => $device->id,
            'customer_id' => $customerId,
            'phone' => $phone,
            'body' => $body,
            'status' => $status,
            'error' => $error,
            'sent_at' => $sendResult['status'] ? now() : null,
        ]);

        if ($sendResult['status']) {
            return back()->with('success', "Pesan pengingat berhasil dikirim ke {$customerName} ({$phone}) via WhatsApp.");
        } else {
            return back()->with('error', "Gagal mengirim WhatsApp: " . $error);
        }
    }

    /**
     * Settings for automation, templates & recipient modes
     */
    public function settings()
    {
        $account = $this->getActiveAccount();
        $connection = $this->getActiveConnection();

        if (!$connection) {
            return redirect()->route('settings.connections');
        }

        $setting = ReminderSetting::firstOrCreate(
            ['connection_id' => $connection->id],
            [
                'enabled' => false,
                'recipient_mode' => 'both',
                'fixed_numbers' => '',
                'send_time' => '09:00:00',
                'rules' => [
                    ['type' => 'due_today', 'label' => 'Saat Hari Jatuh Tempo (H-0)'],
                    ['type' => 'overdue_3', 'label' => 'Lewat Jatuh Tempo 3 Hari (H+3)'],
                    ['type' => 'overdue_7', 'label' => 'Lewat Jatuh Tempo 7 Hari (H+7)'],
                ],
            ]
        );

        $defaultCustomerBody = "Halo Bapak/Ibu *{nama_pelanggan}*,\n\nKami dari *{nama_toko}* menginformasikan bahwa terdapat tagihan yang belum terselesaikan sebesar *{total_piutang}* ({jumlah_nota} nota belum lunas).\n\nMohon untuk melakukan konfirmasi atau penyelesaian pembayaran. Abaikan pesan ini jika sudah melakukan pembayaran. Terima kasih atas kerja samanya.";
        $defaultRecapBody = "*[REKAP PIUTANG JATUH TEMPO]*\nToko: *{nama_toko}*\nTanggal: {tanggal_sekarang}\n\nTotal Piutang Overdue: *{total_piutang}*\nTotal Pelanggan Menunggak: *{total_pelanggan} orang*\n\n{daftar_pelanggan}\n\nSilakan cek detail lengkap di aplikasi modsysv4.";

        $templateCustomer = ReminderTemplate::firstOrCreate(
            ['account_id' => $account->id, 'type' => 'customer'],
            [
                'name' => 'Template Pengingat Pelanggan',
                'body' => $defaultCustomerBody,
            ]
        );

        $templateRecap = ReminderTemplate::firstOrCreate(
            ['account_id' => $account->id, 'type' => 'recap'],
            [
                'name' => 'Template Rekap Owner',
                'body' => $defaultRecapBody,
            ]
        );

        $waDevices = $account->waDevices;
        $history = WaMessage::where('connection_id', $connection->id)->latest()->take(30)->get();

        return view('modules.pengingat_piutang.settings', compact(
            'account',
            'connection',
            'setting',
            'templateCustomer',
            'templateRecap',
            'waDevices',
            'history'
        ));
    }

    public function saveSettings(Request $request)
    {
        $account = $this->getActiveAccount();
        $connection = $this->getActiveConnection();
        $setting = ReminderSetting::firstOrCreate(['connection_id' => $connection->id]);

        $setting->update([
            'enabled' => $request->boolean('enabled'),
            'device_id' => $request->input('device_id'),
            'recipient_mode' => $request->input('recipient_mode', 'both'),
            'fixed_numbers' => $request->input('fixed_numbers'),
            'send_time' => $request->input('send_time', '09:00:00'),
        ]);

        if ($request->has('template_customer')) {
            ReminderTemplate::updateOrCreate(
                ['account_id' => $account->id, 'type' => 'customer'],
                [
                    'name' => 'Template Pengingat Pelanggan',
                    'body' => $request->input('template_customer'),
                ]
            );
        }

        if ($request->has('template_recap')) {
            ReminderTemplate::updateOrCreate(
                ['account_id' => $account->id, 'type' => 'recap'],
                [
                    'name' => 'Template Rekap Owner',
                    'body' => $request->input('template_recap'),
                ]
            );
        }

        return back()->with('success', 'Pengaturan jadwal dan template pesan WhatsApp berhasil disimpan.');
    }
}
