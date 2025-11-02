# Installation Instructions

## Prerequisites

- **OJS**: 3.3.x or 3.4.x
- **PHP**: 7.3 or higher
- **PHP Extensions**: GD or Imagick, mbstring, zip

**Note**: TCPDF library (v6.10.0) is bundled with the plugin - no additional installation required!

## Installation Steps

### Step 1: Install the Plugin

Clone or download the plugin to your OJS installation:

```bash
cd /path/to/ojs/plugins/generic/
git clone https://github.com/ssemerikov/plugin.git reviewerCertificate
```

Or download and extract the ZIP file to `plugins/generic/reviewerCertificate/`

### Step 2: Set Permissions

```bash
chmod -R 755 /path/to/ojs/plugins/generic/reviewerCertificate/
```

### Step 3: Enable the Plugin

1. Log in to OJS as **Administrator** or **Journal Manager**
2. Navigate to: **Settings → Website → Plugins**
3. Find "Reviewer Certificate Plugin" in the list
4. Click the **checkbox** to enable it
5. Click **Settings** to configure certificate options

### Step 4: Configure Certificate Settings

1. In the plugin settings:
   - Set certificate template text
   - Choose fonts and colors
   - Set minimum completed reviews requirement
   - Enable QR code verification (optional)
2. Click **Preview Certificate** to test your design
3. Save settings

## What's Included

The plugin comes with:
- ✅ TCPDF 6.10.0 library (in `lib/tcpdf/`)
- ✅ All required fonts
- ✅ QR code generation support
- ✅ Complete PDF generation system

## Troubleshooting

### Error: "TCPDF library not found"

This should not happen with the bundled version. If you see this error:

1. Verify the `lib/tcpdf/` directory exists in the plugin
2. Check that `lib/tcpdf/tcpdf.php` file exists
3. Ensure proper file permissions (755 or 755 for directories, 644 for files)

### Permission Issues

Set proper permissions:
```bash
chmod -R 755 /path/to/ojs/plugins/generic/reviewerCertificate/
```

### Background Images Not Working

Create upload directory with proper permissions:
```bash
mkdir -p /path/to/ojs/files/journals/[JOURNAL_ID]/reviewerCertificate/
chmod -R 775 /path/to/ojs/files/journals/[JOURNAL_ID]/reviewerCertificate/
chown -R www-data:www-data /path/to/ojs/files/journals/[JOURNAL_ID]/reviewerCertificate/
```

(Replace `www-data` with your web server user)

### Plugin Not Appearing

1. Clear OJS cache:
   - **Settings → Website → Clear Data Cache**
2. Check file permissions
3. Check OJS error logs: `/path/to/ojs/files/error.log`

## Updating

To update the plugin to the latest version:

```bash
cd /path/to/ojs/plugins/generic/reviewerCertificate/
git pull origin main
```

Then clear OJS cache in the admin interface.

## Technical Details

### TCPDF Integration

The plugin includes TCPDF 6.10.0 in the `lib/tcpdf/` directory. The CertificateGenerator class automatically detects and loads TCPDF from:

1. Plugin's bundled TCPDF (primary): `lib/tcpdf/tcpdf.php`
2. OJS 3.4 location (fallback): `lib/pkp/lib/vendor/tecnickcom/tcpdf/tcpdf.php`
3. OJS 3.3 location (fallback): `lib/pkp/lib/tcpdf/tcpdf.php`

This ensures maximum compatibility across different OJS installations.

### File Structure

```
reviewerCertificate/
├── classes/
│   ├── Certificate.inc.php
│   ├── CertificateDAO.inc.php
│   ├── CertificateGenerator.inc.php
│   └── form/
├── controllers/
├── locale/
├── lib/
│   └── tcpdf/              ← TCPDF library (bundled)
│       ├── tcpdf.php
│       ├── fonts/
│       ├── config/
│       └── ...
├── templates/
├── ReviewerCertificatePlugin.inc.php
└── version.xml
```

## Uninstallation

1. Disable the plugin in OJS admin
2. Remove the plugin directory:
   ```bash
   rm -rf /path/to/ojs/plugins/generic/reviewerCertificate/
   ```

The plugin's database tables will remain. To remove them:

```sql
DROP TABLE IF EXISTS certificates;
```

## Support

- **Issues**: https://github.com/ssemerikov/plugin/issues
- **Documentation**: See README.md
- **OJS Forums**: https://forum.pkp.sfu.ca/

## License

GNU General Public License v3.0
