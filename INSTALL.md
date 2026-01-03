# Reviewer Certificate Plugin - Installation Guide

## Quick Install via OJS Admin (Recommended)

The easiest way to install the plugin - no command line needed!

1. **Download** `reviewerCertificate.tar.gz` from [GitHub Releases](https://github.com/ssemerikov/reviewerCertificate/releases)

2. **Log in** to OJS as Administrator

3. **Navigate to** Settings → Website → Plugins

4. **Click** "Upload A New Plugin" button

5. **Select** the downloaded `reviewerCertificate.tar.gz` file

6. **Click** "Save" to upload and install

7. **Enable** the "Reviewer Certificate Plugin"

8. **Click** "Settings" to customize certificate templates

**That's it!** The database tables will be created automatically.

---

## Alternative: Git Clone Installation

For developers or if the tar.gz method doesn't work:

1. **Upload the plugin:**
   ```bash
   cd /path/to/ojs/plugins/generic/
   git clone https://github.com/ssemerikov/reviewerCertificate.git
   ```

2. **Set permissions:**
   ```bash
   chmod -R 755 reviewerCertificate/
   chown -R www-data:www-data reviewerCertificate/  # Adjust user as needed
   ```

3. **Enable the plugin:**
   - Log in to OJS as Administrator
   - Go to **Settings → Website → Plugins**
   - Find "Reviewer Certificate Plugin"
   - Click **Enable**
   - The database tables will be created automatically

4. **Configure:**
   - Click **Settings** to customize certificate templates
   - Click **Preview Certificate** to test your design

---

## Manual Installation (If Automatic Fails)

If you encounter errors like "Table 'reviewer_certificates' doesn't exist" or migration failures, follow these steps:

### Step 1: Install Plugin Files

```bash
cd /path/to/ojs/plugins/generic/
git clone https://github.com/ssemerikov/reviewerCertificate.git
chmod -R 755 reviewerCertificate/
```

### Step 2: Create Database Tables Manually

**Option A: Using MySQL Command Line**

```bash
cd /path/to/ojs/plugins/generic/reviewerCertificate/
mysql -u [username] -p [database_name] < install.sql
```

**Option B: Using phpMyAdmin**

1. Open phpMyAdmin
2. Select your OJS database
3. Go to the **SQL** tab
4. Copy and paste the contents of `install.sql`
5. Click **Go**

### Step 3: Verify Installation

Check that tables were created:

```sql
SHOW TABLES LIKE 'reviewer_certificate%';
```

You should see:
- `reviewer_certificate_templates`
- `reviewer_certificates`
- `reviewer_certificate_settings`

### Step 4: Enable Plugin in OJS

1. Log in to OJS as Administrator
2. Go to **Settings → Website → Plugins**
3. Find "Reviewer Certificate Plugin"
4. Click **Enable**
5. Click **Settings** to configure

---

## Troubleshooting

### Error: "Table 'reviewer_certificates' doesn't exist"

**Cause:** Database migration failed to run automatically.

**Solution:** Install tables manually using `install.sql`.

### Error: "Failed Ajax request or invalid JSON returned"

**Cause:** Database tables are missing.

**Solution:**
1. Run the manual SQL installation (see above)
2. Refresh the page
3. Try enabling the plugin again

### Error: "Call to a member function connection() on null"

**Cause:** OJS 3.3 migration system issue.

**Solution:** Use manual SQL installation instead of command-line migration.

---

## Support

Report issues at: https://github.com/ssemerikov/reviewerCertificate/issues
