# Certificate Download Test

## Quick Test on Server

Run this command on the server to see recent certificates:

```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate
php QUERY_CERTIFICATES.php
```

## Test Certificates Generated (from logs)

| Certificate ID | Review ID | Reviewer ID | Code |
|---------------|-----------|-------------|------|
| 122 | 453 | 73 | 4907A7A7C7D5 |
| 123 | 455 | 73 | 7F07EF2DA579 |
| 124 | 458 | 73 | C14620571A39 |
| 145 | 1715 | 73 | 1579A44EF966 |
| 146 | 1721 | 73 | 3A446EF8E3F8 |
| 147 | 1729 | 73 | 5123979F74EB |

Total: 26 certificates created for reviewer ID 73

## Test URLs

### 1. Certificate Verification (Public - No Login Required)
```
https://acnsci.org/journal/index.php/cte/certificate/verify/4907A7A7C7D5
https://acnsci.org/journal/index.php/cte/certificate/verify/1579A44EF966
https://acnsci.org/journal/index.php/cte/certificate/verify/5123979F74EB
```

**Expected Result**: Shows certificate details (reviewer name, submission, date issued, etc.)

### 2. Certificate Download (Requires Login as Reviewer)

Download via review ID (reviewer must be logged in as reviewer ID 73):
```
https://acnsci.org/journal/index.php/cte/certificate/download/453
https://acnsci.org/journal/index.php/cte/certificate/download/1715
https://acnsci.org/journal/index.php/cte/certificate/download/1729
```

**Expected Result**: Downloads PDF certificate file

## Test Steps

1. **Test Verification (Public Access)**
   - Open: https://acnsci.org/journal/index.php/cte/certificate/verify/4907A7A7C7D5
   - Should show certificate details without login

2. **Test Download (Requires Login as Reviewer)**
   - Log in as reviewer ID 73
   - Navigate to review ID 453 or completed reviews dashboard
   - Click certificate download button
   - Should download PDF certificate

3. **Database Verification**
   ```sql
   SELECT certificate_id, review_id, certificate_code, download_count
   FROM reviewer_certificates
   WHERE reviewer_id = 73
   ORDER BY certificate_id DESC
   LIMIT 5;
   ```

## Expected Results

- ✅ Verification URL shows certificate information
- ✅ Download increments download_count in database
- ✅ PDF certificate contains reviewer name, submission title, date
- ✅ QR code on certificate links to verification page
