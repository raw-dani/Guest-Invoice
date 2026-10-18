<?php

namespace GuestInvoice\Lib;

use WHMCS\Database\Capsule;

class MailService
{
    /**
     * Send guest invoice link to client via WHMCS official email system.
     *
     * @param int $invoiceId
     * @param string $rawToken
     * @param string $expiresAt
     * @param int|null $adminId
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function sendGuestInvoiceLink(int $invoiceId, string $rawToken, string $expiresAt, ?int $adminId = null): array
    {
        try {
            $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
            if (!$invoice) {
                return ['success' => false, 'error' => "Invoice #{$invoiceId} not found."];
            }

            $client = Capsule::table('tblclients')->where('id', $invoice->userid)->first();
            if (!$client) {
                return ['success' => false, 'error' => "Client profile not found for invoice #{$invoiceId}."];
            }

            $guestUrl = TokenService::buildGuestUrl($rawToken);
            $companyName = InvoiceService::getWhmcsConfig('CompanyName', 'Billing Portal');
            $currency = Capsule::table('tblcurrencies')->where('id', $client->currency)->first();
            $prefix = $currency->prefix ?? '';
            $suffix = $currency->suffix ?? '';
            $amountFormatted = $prefix . number_format((float)$invoice->total, 2) . $suffix;

            $customVars = [
                'client_name' => trim($client->firstname . ' ' . $client->lastname),
                'invoice_id' => $invoice->id,
                'invoice_num' => !empty($invoice->invoicenum) ? $invoice->invoicenum : (string)$invoice->id,
                'invoice_total' => $amountFormatted,
                'invoice_date' => $invoice->date,
                'invoice_due_date' => $invoice->duedate,
                'guest_invoice_url' => $guestUrl,
                'link_expires_at' => date('d M Y, H:i', strtotime($expiresAt)),
                'company_name' => $companyName,
            ];

            // If WHMCS LocalAPI is present, dispatch via official template or direct email
            if (function_exists('localAPI')) {
                // Check if specific email template exists
                $templateExists = Capsule::table('tblemailtemplates')
                    ->where('name', 'Guest Invoice Link Delivery')
                    ->exists();

                if ($templateExists) {
                    $apiResult = localAPI('SendEmail', [
                        'messagename' => 'Guest Invoice Link Delivery',
                        'id' => $client->id,
                        'customvars' => base64_encode(serialize($customVars)),
                    ]);
                } else {
                    // Send as general client notification
                    $subject = "Invoice #{$customVars['invoice_num']} — Payment Link";
                    $body = self::getDefaultEmailHtml($customVars);

                    $apiResult = localAPI('SendEmail', [
                        'customtype' => 'general',
                        'customsubject' => $subject,
                        'custommessage' => $body,
                        'id' => $client->id,
                    ]);
                }

                $success = (($apiResult['result'] ?? '') === 'success');
                if (!$success) {
                    return ['success' => false, 'error' => $apiResult['message'] ?? 'WHMCS SendEmail API failed.'];
                }
            }

            AuditLogger::log([
                'invoice_id' => $invoiceId,
                'event_type' => 'email_sent',
                'result' => 'success',
                'actor_type' => $adminId ? 'admin' : 'system',
                'actor_id' => $adminId,
                'details' => json_encode([
                    'recipient' => InvoiceService::maskEmail($client->email),
                    'invoice_num' => $customVars['invoice_num'],
                ]),
            ]);

            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Email sending error: ' . $e->getMessage()];
        }
    }

    /**
     * Default professional responsive HTML email body.
     */
    public static function getDefaultEmailHtml(array $vars): string
    {
        return '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;">
            <div style="margin-bottom: 24px; border-bottom: 2px solid #3b82f6; padding-bottom: 16px;">
                <h2 style="margin: 0; color: #1e293b; font-size: 22px;">' . htmlspecialchars($vars['company_name']) . '</h2>
                <p style="margin: 4px 0 0 0; color: #64748b; font-size: 14px;">Pemberitahuan Tagihan Pembayaran</p>
            </div>
            <p style="color: #334155; font-size: 15px; line-height: 1.6;">Halo <strong>' . htmlspecialchars($vars['client_name']) . '</strong>,</p>
            <p style="color: #334155; font-size: 15px; line-height: 1.6;">Berikut adalah tautan akses langsung untuk melihat detail dan melakukan pembayaran tagihan Anda tanpa perlu login ke portal:</p>
            
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin: 20px 0;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px; color: #334155;">
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Nomor Tagihan:</td>
                        <td style="padding: 6px 0; font-weight: bold; text-align: right;">#' . htmlspecialchars($vars['invoice_num']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Jumlah Tagihan:</td>
                        <td style="padding: 6px 0; font-weight: bold; color: #2563eb; text-align: right;">' . htmlspecialchars($vars['invoice_total']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Jatuh Tempo:</td>
                        <td style="padding: 6px 0; text-align: right;">' . htmlspecialchars($vars['invoice_due_date']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Tautan Berlaku Hingga:</td>
                        <td style="padding: 6px 0; color: #dc2626; text-align: right;">' . htmlspecialchars($vars['link_expires_at']) . '</td>
                    </tr>
                </table>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="' . htmlspecialchars($vars['guest_invoice_url']) . '" style="background: #2563eb; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; display: inline-block;">Lihat & Bayar Tagihan</a>
            </div>

            <p style="color: #64748b; font-size: 13px; line-height: 1.5;">Atau salin tautan berikut ke peramban Anda:<br>
            <a href="' . htmlspecialchars($vars['guest_invoice_url']) . '" style="color: #2563eb; word-break: break-all;">' . htmlspecialchars($vars['guest_invoice_url']) . '</a></p>

            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
            <p style="color: #94a3b8; font-size: 12px; margin: 0; text-align: center;">Tautan ini bersifat rahasia dan dirancang khusus untuk Anda. Jangan bagikan kepada pihak yang tidak berwenang.</p>
        </div>';
    }
}
