# Reviewer Certificate Plugin for OJS

## Overview

The Reviewer Certificate Plugin enables reviewers to generate and download personalized PDF certificates of recognition after completing peer reviews. This plugin helps journals acknowledge and incentivize quality peer review work.

## Features

- **Automated Certificate Generation**: Certificates are automatically available when reviewers complete their reviews
- **Customizable Templates**: Each journal can design unique certificate templates with custom backgrounds, fonts, and colors
- **Dynamic Content**: Insert reviewer names, journal names, submission titles, and dates using template variables
- **Eligibility Criteria**: Set minimum review requirements before certificates become available
- **QR Code Verification**: Include QR codes for certificate authenticity verification
- **Download Tracking**: Track certificate downloads and usage statistics
- **Multi-language Support**: Full internationalization support
- **Batch Generation**: Generate certificates for multiple reviewers at once

## Requirements

- **OJS Version**: 3.3.x or 3.4.x
- **PHP**: 7.3 or higher
- **Required PHP Extensions**:
  - GD or Imagick (for image processing)
  - mbstring
  - zip
- **TCPDF Library**: ✅ Bundled with plugin (v6.10.0) - no additional installation required!

## Installation

### Quick Install (Recommended)

1. Clone or download the plugin:
   ```bash
   cd /path/to/ojs/plugins/generic/
   git clone https://github.com/ssemerikov/plugin.git reviewerCertificate
   ```

2. Set permissions:
   ```bash
   chmod -R 755 reviewerCertificate/
   ```

3. Enable in OJS:
   - Log in as Administrator
   - Go to **Settings → Website → Plugins**
   - Find "Reviewer Certificate Plugin"
   - Click **Enable**

4. Configure and use:
   - Click **Settings** to customize certificate templates
   - Click **Preview Certificate** to test your design

**That's it!** The plugin includes TCPDF library, so no additional dependencies need to be installed.

### Manual Installation

1. Download the plugin package
2. Extract to: `plugins/generic/reviewerCertificate/`
3. Set permissions: `chmod -R 755 plugins/generic/reviewerCertificate/`
4. Enable in OJS admin interface

Then follow steps 4-8 from Method 1.

## Configuration

### Initial Setup

1. Navigate to: **Settings > Website > Plugins**

2. Find **Reviewer Certificate Plugin** and click **Settings**

3. Configure the following options:

#### Template Settings

- **Background Image**: Upload a custom background image (2100x2970 px recommended)
- **Header Text**: The main heading (e.g., "Certificate of Recognition")
- **Body Template**: The main certificate content with template variables
- **Footer Text**: Optional footer text

#### Appearance Settings

- **Font Family**: Choose from Helvetica, Times New Roman, Courier, or DejaVu Sans
- **Font Size**: Set the base font size (default: 12pt)
- **Text Color**: Set RGB values for text color (0-255 for each component)

#### Eligibility Criteria

- **Minimum Reviews**: Set how many completed reviews are required (default: 1)
- **Include QR Code**: Enable/disable QR code for verification

### Template Variables

Use these variables in your templates to insert dynamic content:

| Variable | Description |
|----------|-------------|
| `{{$reviewerName}}` | Full name of the reviewer |
| `{{$reviewerFirstName}}` | Reviewer's first name |
| `{{$reviewerLastName}}` | Reviewer's last name |
| `{{$journalName}}` | Full journal name |
| `{{$journalAcronym}}` | Journal acronym |
| `{{$submissionTitle}}` | Title of the reviewed manuscript |
| `{{$reviewDate}}` | Date review was completed |
| `{{$reviewYear}}` | Year of review completion |
| `{{$currentDate}}` | Current date |
| `{{$currentYear}}` | Current year |
| `{{$certificateCode}}` | Unique certificate verification code |

### Example Certificate Template

```
Certificate of Recognition

This certificate is awarded to

{{$reviewerName}}

In recognition of their valuable contribution as a peer reviewer for

{{$journalName}}

Review completed on {{$reviewDate}}

Manuscript: {{$submissionTitle}}

We deeply appreciate your expertise and dedication to advancing scholarly communication.
```

## Usage

### For Reviewers

1. Complete a peer review assignment
2. Submit your review
3. If eligible, a certificate download button will appear in your reviewer dashboard
4. Click **Download Certificate** to generate and download your PDF certificate
5. The certificate includes a unique verification code for authenticity

### For Journal Managers

#### Viewing Certificate Statistics

1. Navigate to: **Settings > Website > Plugins > Reviewer Certificate Plugin**
2. View statistics on issued certificates and downloads

#### Batch Certificate Generation

1. Navigate to the plugin settings
2. Select multiple reviewers
3. Click **Generate Batch Certificates**
4. Certificates will be generated for all eligible completed reviews

#### Certificate Verification

To verify a certificate's authenticity:

1. Navigate to: `[your-journal-url]/certificate/verify/[certificate-code]`
2. The system will display certificate details if valid

## Customization

### Custom Background Design

Create a professional certificate background:

1. **Dimensions**: 210mm x 297mm (A4)
2. **Resolution**: 300 DPI
3. **Format**: JPG or PNG
4. **Margins**: Leave at least 15mm on all sides for text
5. **Design Elements**: Add borders, seals, logos, or watermarks

### Styling Certificates

Modify the appearance using the settings form:

- Adjust font family and size
- Change text colors using RGB values
- Position elements using the layout settings
- Preview changes before saving

### Advanced Customization

For developers who want to customize further:

1. **Modify CertificateGenerator.inc.php**: Customize PDF generation logic
2. **Edit certificate.css**: Change button and interface styling
3. **Update certificate.js**: Add custom JavaScript functionality
4. **Extend locale files**: Add translations for additional languages

## Troubleshooting

### Certificates Not Appearing

- Verify the plugin is enabled
- Check that reviews are marked as completed
- Confirm minimum review requirements are met
- Check file permissions on the files directory

### PDF Generation Errors

- Ensure TCPDF library is properly installed
- Verify PHP memory limit (recommended: 256MB)
- Check PHP error logs for specific issues
- Ensure GD or Imagick extension is enabled

### Background Image Issues

- Verify image format (JPG or PNG only)
- Check image file size (keep under 5MB)
- Ensure correct file permissions
- Use recommended dimensions (2100x2970 pixels)

### Download Permission Errors

- Verify reviewer is logged in
- Check that reviewer owns the review assignment
- Ensure review is marked as completed
- Check server file permissions

## API Endpoints

The plugin provides these API endpoints:

- `GET /certificate/download/{reviewId}` - Download certificate
- `GET /certificate/verify/{certificateCode}` - Verify certificate
- `GET /certificate/preview` - Preview certificate template
- `POST /certificate/generateBatch` - Generate batch certificates

## Security Considerations

- Certificates include unique verification codes
- Access control ensures reviewers can only access their own certificates
- QR codes link to verification endpoints
- Download tracking for audit purposes
- CSRF protection on all forms
- File upload validation and sanitization

## Database Schema

The plugin creates these tables:

- `reviewer_certificate_templates` - Template configurations
- `reviewer_certificates` - Issued certificates
- `reviewer_certificate_settings` - Localized settings

## Performance

- PDF generation is optimized for quick response
- Background images are cached
- Database queries are indexed
- Supports high-volume certificate generation

## Compatibility

- **OJS 3.3.x**: Fully compatible
- **OJS 3.4.x**: Fully compatible
- **PHP 7.3+**: Required
- **PHP 8.0+**: Compatible

## Support

For issues, questions, or feature requests:

1. Check the [documentation](#)
2. Search existing [issues](#)
3. Create a new issue with:
   - OJS version
   - PHP version
   - Error messages
   - Steps to reproduce

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This plugin is licensed under the GNU General Public License v3.0.

Copyright (c) 2024

## Changelog

### Version 1.0.0 (2024-01-01)

- Initial release
- Basic certificate generation
- Customizable templates
- QR code verification
- Multi-language support
- Batch generation capability

## Credits

Developed for the Open Journal Systems community to support and recognize peer reviewers' contributions to scholarly publishing.

## Additional Resources

- [OJS Documentation](https://docs.pkp.sfu.ca/learning-ojs/)
- [PKP Community Forum](https://forum.pkp.sfu.ca/)
- [TCPDF Documentation](https://tcpdf.org/)

---

**Note**: Always backup your database and files before installing new plugins.
