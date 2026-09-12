<?php
namespace APP\plugins\generic\reviewerCertificate\classes;

/** One mail boundary for availability and reviewer-requested PDF acknowledgements. */
class CertificateMailAdapter {
    private static $pending = [];
    private static $dispatchers = [];

    private function setting($context, $key) {
        $value = method_exists($context, 'getData') ? $context->getData($key) : null;
        if (!$value && method_exists($context, 'getSetting')) { $value = $context->getSetting($key); }
        return is_array($value) ? (string) reset($value) : (string) $value;
    }

    public function availability($user, $context, $certificate, $request) {
        // Settings actions run under the component router, which cannot build
        // page URLs with path arguments. Select the page router explicitly.
        $dispatcher = $request->getDispatcher();
        $pageRoute = defined('ROUTE_PAGE') ? ROUTE_PAGE : \PKP\core\PKPApplication::ROUTE_PAGE;
        $params = ['reviewerName' => $user->getFullName(), 'journalName' => $context->getLocalizedName(),
            'certificateUrl' => $dispatcher->url($request, $pageRoute, $context->getPath(), 'certificate', 'download', [$certificate->getReviewId()]),
            'journalUrl' => $dispatcher->url($request, $pageRoute, $context->getPath())];
        $subject = __('plugins.generic.reviewerCertificate.email.subject');
        $body = __('plugins.generic.reviewerCertificate.email.body');
        if (class_exists('APP\facades\Repo')) {
            $template = \APP\facades\Repo::emailTemplate()->getByKey($context->getId(), 'REVIEWER_CERTIFICATE_AVAILABLE');
            if ($template) {
                $locale = $context->getPrimaryLocale();
                $subject = $template->getLocalizedData('subject', $locale) ?: $subject;
                $body = $template->getLocalizedData('body', $locale) ?: $body;
            }
        } else {
            if (function_exists('import')) { import('lib.pkp.classes.mail.MailTemplate'); }
            if (class_exists('MailTemplate')) {
                $template = new \MailTemplate('REVIEWER_CERTIFICATE_AVAILABLE', $context->getPrimaryLocale(), $context, false);
                $subject = $template->getSubject() ?: $subject;
                $body = $template->getBody() ?: $body;
            }
        }
        if (!$subject || strpos($subject, '##') === 0) { $subject = 'Your review certificate is ready'; }
        if (!$body || strpos($body, '##') === 0) {
            $body = 'Dear {$reviewerName},<br>Your certificate from {$journalName} is available: {$certificateUrl}';
        }
        foreach ($params as $key => $value) {
            $subject = str_replace(['{{$' . $key . '}}', '{$' . $key . '}'], strip_tags($value), $subject);
            $body = str_replace(['{{$' . $key . '}}', '{$' . $key . '}'], htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), $body);
        }
        return $this->send($user, $context, $subject, nl2br($body), null, null, $request);
    }

    public function send($user, $context, $subject, $html, $pdf, $filename, $request) {
        $email = $this->setting($context, 'contactEmail');
        $name = $this->setting($context, 'contactName');
        if (!$email && method_exists($request, 'getSite') && ($site = $request->getSite())) {
            $email = $this->setting($site, 'contactEmail');
            $name = $name ?: $this->setting($site, 'contactName');
        }
        $email = $email ?: 'noreply@' . (parse_url($request->getBaseUrl(), PHP_URL_HOST) ?: 'localhost');
        $name = $name ?: $context->getLocalizedName();
        if (class_exists('PKP\mail\Mailable')) {
            require_once __DIR__ . '/ReviewerCertificateAckMailable.php';
            $mail = new ReviewerCertificateAckMailable();
            $mail->from($email, $name)->to($user->getEmail(), $user->getFullName())->subject($subject)->body($html);
            $mail->replyTo($email, $name);
            if ($pdf !== null) { $mail->attachData($pdf, $filename, ['mime' => 'application/pdf']); }
            $token = bin2hex(random_bytes(16));
            $mail->addData(['rcDeliveryToken' => $token]);
            $dispatcher = \Illuminate\Support\Facades\Event::getFacadeRoot();
            if (!in_array($dispatcher, self::$dispatchers, true)) {
                $dispatcher->listen(\Illuminate\Mail\Events\MessageSent::class, function ($event) {
                    $key = $event->data['rcDeliveryToken'] ?? null;
                    if (is_string($key) && array_key_exists($key, self::$pending)) { self::$pending[$key] = true; }
                });
                self::$dispatchers[] = $dispatcher;
            }
            self::$pending[$token] = false;
            try {
                \Illuminate\Support\Facades\Mail::send($mail);
                return self::$pending[$token];
            } catch (\Throwable $error) {
                // A later observer may throw after the transport accepted this
                // exact message. Do not turn known acceptance into a retry.
                if (self::$pending[$token]) { return true; }
                throw $error;
            } finally { unset(self::$pending[$token]); }
        }
        if (function_exists('import')) { import('lib.pkp.classes.mail.Mail'); }
        if (!class_exists('Mail')) { return false; }
        $mail = new \Mail();
        $mail->setFrom($email, $name);
        $mail->setReplyTo($email, $name);
        $mail->addRecipient($user->getEmail(), $user->getFullName());
        $mail->setSubject($subject);
        $mail->setBody($html);
        $tmp = null;
        try {
            if ($pdf !== null) {
                $tmp = tempnam(sys_get_temp_dir(), 'rc_cert_');
                if (!$tmp || file_put_contents($tmp, $pdf) !== strlen($pdf)) { return false; }
                $mail->addAttachment($tmp, $filename, 'application/pdf');
            }
            return (bool) $mail->send();
        } finally { if ($tmp && is_file($tmp)) { unlink($tmp); } }
    }
}
