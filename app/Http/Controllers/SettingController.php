<?php

namespace App\Http\Controllers;

use App\Http\Requests\Setting\UpdateSettingsRequest;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SettingController extends Controller
{
    /** Plain (non-encrypted) setting keys editable on the form. */
    private const PLAIN = [
        'company_name', 'currency', 'currency_symbol', 'tax_percent',
        'default_partner_discount', 'default_commission_percent', 'default_sponsor_bonus_percent',
        'max_partner_depth', 'mail_host', 'mail_port', 'mail_username', 'mail_encryption',
        'mail_from_address', 'mail_from_name',
    ];

    public function __construct(private readonly SettingService $settings) {}

    public function index(): mixed
    {
        $all = $this->settings->all();
        unset($all['mail_password']); // never expose the stored (encrypted) password

        return view('admin.settings.index', ['settings' => $all]);
    }

    public function update(UpdateSettingsRequest $request): mixed
    {
        $data = $request->validated();

        $pairs = [];
        foreach (self::PLAIN as $key) {
            if (array_key_exists($key, $data)) {
                $pairs[$key] = ['value' => $data[$key]];
            }
        }
        // Password only when provided; stored encrypted.
        if (! empty($data['mail_password'])) {
            $pairs['mail_password'] = ['value' => $data['mail_password'], 'encrypt' => true];
        }

        $this->settings->setMany($pairs);

        return $this->ok(null, 'Settings saved.');
    }

    public function testEmail(Request $request): mixed
    {
        // MailConfigService (AppServiceProvider::boot) already applied DB creds.
        try {
            Mail::raw('This is a test email from '.config('app.name').'.', function ($m) use ($request) {
                $m->to($request->user()->email)->subject(config('app.name').' — test email');
            });
        } catch (\Throwable $e) {
            return $this->fail('Send failed: '.$e->getMessage(), 422);
        }

        return $this->ok(null, 'Test email sent to '.$request->user()->email.'.');
    }
}
