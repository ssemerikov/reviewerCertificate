# Email Certificate to Reviewer — Design

**Date:** 2026-07-04
**Requested by:** maintainer (session request)
**Status:** implemented in same session (autonomous mode — interactive design gates
collapsed inline; decisions marked *[default]* were made without user input and are
easy to change)

## Goal

On the **My Certificates** page, next to each certificate's Download button, add an
action that emails the reviewer the journal's acknowledgement letter **with the
certificate PDF attached**. The letter text is editable in plugin settings and is
modeled on OJS's "Article Review Acknowledgement" template plus the sample text
provided by the maintainer.

## Decisions

| Topic | Decision |
|---|---|
| Recipient | The logged-in reviewer's own account email (reviewer triggers it for themselves; the letter is journal-branded, e.g. for forwarding to reviews@webofscience.com) |
| Sender/From | Journal principal contact (`contactName` / `contactEmail`); reply-to same *[default]* |
| Endpoint | `certificate/emailCertificate/{reviewId}`, POST only, CSRF-checked, reviewer role, same ownership+context checks as `download()` (shared helper) |
| Template storage | Two per-journal plugin settings: `ackEmailSubject`, `ackEmailBody` (same non-localized model as existing settings like `headerText`) *[default]* |
| Placeholders | Same engine as PDF body: `{{$reviewerName}}`, `{{$submissionTitle}}`, `{{$journalName}}`, `{{$journalAcronym}}`, `{{$reviewDate}}`, `{{$reviewYear}}`, `{{$currentDate}}`, `{{$certificateCode}}` + new `{{$editorName}}` |
| Defaults | Locale keys `emailCertificate.defaultSubject` / `emailCertificate.defaultBody`; body = maintainer's sample parameterized. Default body stays English in all 32 languages (formal letter, journals customize) *[default]* |
| Sending | OJS 3.4/3.5: `PKP\mail\Mailable` subclass + `Illuminate\Support\Facades\Mail::send()`, attachment via `attachData()`. OJS 3.3: legacy `Mail` class + temp-file `addAttachment()` |
| Attachment | Same PDF bytes as download (`buildCertificatePDF()` extracted from `generateAndOutputPDF()`); filename `reviewer_certificate_{id}.pdf`; increments no download counter *[default]* |
| Result UX | Redirect back to `myCertificates` with `?emailSent=1` or `?emailError=1`; template renders a success/error banner |
| E2E | New `mailpit` service in ojs-test compose (SMTP :1025, API :8125); OJS containers configured for SMTP; spec asserts banner AND message receipt + attachment via Mailpit API on all three OJS versions |

## Components

1. `controllers/CertificateHandler.php`
   - `emailCertificate($args, $request)` — POST+CSRF; loads review assignment via
     shared `loadAuthorizedReviewAssignment()` (extracted from `download()`); gets or
     creates certificate (same as download); builds PDF bytes; sends email; redirects.
   - `buildCertificatePDF()` extracted from `generateAndOutputPDF()`.
2. `classes/CertificateGenerator.php`
   - public `renderText($template)` — exposes `replaceVariables(getTemplateVariables())`.
   - `editorName` added to template variables (journal contact name, 3.3/3.4+ compat).
3. `classes/ReviewerCertificateAckMailable.php` — minimal `PKP\mail\Mailable` subclass
   (3.4/3.5 only; class file loaded conditionally).
4. `classes/form/CertificateSettingsForm.php` + `templates/certificateSettings.tpl` —
   new subject/body fields with defaults from locale keys.
5. `templates/myCertificates.tpl` — per-row POST form with `{csrf}`; result banners.
6. Role assignment: add `emailCertificate` to reviewer ops.
7. Locale: 8 new keys × all languages; short-code dirs synced by converter script.

## Error handling

- Send failure → log + redirect with `emailError=1` (no stack traces to user).
- PDF failure → same path as download (500 + logged detail).
- CSRF/ownership failure → 403, as download does.

## Testing

- Unit: `renderText()` substitution incl. `editorName`; default locale keys exist and
  contain required placeholders; handler op registered for reviewer role.
- E2E (`my-certificates-email.spec.ts`): reviewer clicks Email button → success banner →
  Mailpit API shows message to reviewer with PDF attachment. Runs on ojs33/34/35.
