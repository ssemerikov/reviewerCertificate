# Reviewer Certificate Workflow

## How Reviewers Access Their Certificates

### Overview
This plugin automatically generates and makes certificates available to reviewers after they complete their peer review assignments. Reviewers can download their certificates directly from their reviewer dashboard.

### For Journal Administrators

#### Initial Setup
1. **Install and Enable the Plugin**
   - Navigate to Settings → Website → Plugins → Generic Plugins
   - Find "Reviewer Certificate Plugin" and enable it
   - Click "Settings" to configure certificate templates

2. **Configure Certificate Settings**
   - **Template Design**: Customize the certificate header, body text, and footer
   - **Appearance**: Choose font family, size, and text color
   - **Background Image**: Upload a professional background (optional)
   - **Eligibility**: Set minimum number of completed reviews required (default: 1)
   - **QR Code**: Enable verification QR codes (optional)

3. **Preview Your Design**
   - Use the "Preview Certificate" button to see how certificates will look
   - Make adjustments until you're satisfied with the design

#### How It Works Automatically
Once configured, the plugin works automatically:

1. **Review Completion**: When a reviewer completes a review, a certificate record is created automatically
2. **Button Appears**: The certificate download button appears in the reviewer's dashboard
3. **No Manual Action Required**: Administrators don't need to manually generate certificates for each review

#### Batch Certificate Generation
For existing completed reviews (before plugin installation):

1. Open plugin settings
2. Scroll to "Batch Certificate Generation" section
3. Select reviewers who have completed reviews but don't have certificates yet
4. Click "Generate Certificates"
5. Certificates are created for all their completed reviews

### For Reviewers

#### Where to Find Certificates

**Step 1: Complete a Review**
- Reviewers must complete their assigned peer review
- Submit their review through the standard OJS review process

**Step 2: Access Reviewer Dashboard**
- After completing a review, reviewers should:
  1. Log into the journal website
  2. Navigate to their "Submissions" page or "Dashboard"
  3. Look for their completed review assignments

**Step 3: Download Certificate**
- On the review completion page, reviewers will see a blue box with:
  - Heading: "Your Certificate is Ready!"
  - Description: "Thank you for completing this review. You can now download your certificate of recognition."
  - Button: "Download Certificate"

**Step 4: Save the PDF**
- Click the "Download Certificate" button
- A PDF file will download automatically
- Save it to your computer or professional portfolio

#### Certificate Features
Each certificate includes:
- Reviewer's full name
- Journal name
- Manuscript title reviewed
- Review completion date
- Unique certificate code for verification
- Optional QR code (if enabled by journal)

#### Multiple Reviews
- Reviewers receive one certificate per completed review
- Each certificate is unique with its own verification code
- All certificates can be downloaded from their respective review pages

#### Troubleshooting for Reviewers

**"I don't see a certificate button"**
- Make sure you've fully completed and submitted your review
- Check that you meet the minimum review requirements (set by the journal)
- Contact the journal editor if you believe you're eligible but don't see the button

**"The download button doesn't work"**
- Try refreshing the page
- Clear your browser cache
- Try a different browser
- Contact journal technical support

**"I want a certificate for an old review"**
- Ask the journal administrator to use the "Batch Certificate Generation" feature
- They can generate certificates for all your past completed reviews

### Technical Details

#### Where Certificates Appear in OJS
The certificate download button appears on these OJS pages:
- `reviewer/review/reviewCompleted.tpl` - Primary review completion page
- `reviewer/review/step3.tpl` - Alternative completion page (OJS version dependent)
- `reviewer/review/step4.tpl` - Additional completion page pattern

#### Template Hook
The plugin uses the OJS template hook: `TemplateManager::display`
- Automatically detects completed reviews
- Checks reviewer eligibility based on configured criteria
- Injects certificate download button into the review interface

#### Certificate Generation
- **On Review Completion**: Automatic (if reviewer meets minimum review requirements)
- **Batch Generation**: Manual (for past reviews or after plugin installation)
- **On-Demand**: Generated dynamically when reviewer clicks download button

#### Storage
- Certificate metadata stored in database table: `reviewer_certificates`
- PDFs generated on-demand (not pre-generated)
- Background images stored in: `files/journals/[journal-id]/reviewerCertificate/`

### FAQ

**Q: Do reviewers get notified by email?**
A: Yes, reviewers receive an email notification when their certificate becomes available (if email templates are configured).

**Q: Can reviewers download their certificate multiple times?**
A: Yes, they can download it as many times as needed.

**Q: What if a reviewer loses their certificate?**
A: They can log back into their account and download it again from the same review page.

**Q: Can certificates be verified?**
A: Yes, if QR codes are enabled, anyone can scan the QR code or visit the verification URL to confirm authenticity.

**Q: What information do I need to verify a certificate?**
A: Just the unique certificate code printed on the certificate. Enter it at: [journal-url]/certificate/verify/[code]

**Q: Can I customize the certificate design?**
A: Yes, administrators can customize header text, body template, fonts, colors, and add a background image.

**Q: What file format are certificates?**
A: Certificates are generated as PDF files, which can be printed or shared digitally.

**Q: Are certificates generated for all reviewers?**
A: Only for reviewers who meet the eligibility criteria (e.g., minimum number of completed reviews).

### Support

For technical issues or questions:
1. Check the error logs at `files/error.log` (administrators)
2. Review this documentation
3. Contact the plugin developer
4. Submit issues on GitHub: https://github.com/ssemerikov/reviewerCertificate

### Best Practices

**For Journal Administrators:**
- Configure attractive, professional-looking certificates
- Test the preview function before going live
- Inform reviewers about the certificate feature in invitation emails
- Use batch generation to create certificates for past reviews
- Periodically check the statistics dashboard

**For Promoting the Feature:**
- Mention certificate availability in reviewer invitation emails
- Add information to journal's "For Reviewers" page
- Include in reviewer guidelines
- Highlight in social media or newsletters

**For Certificate Design:**
- Use high-quality background images (2100x2970px for A4)
- Keep text readable with appropriate font sizes (12pt minimum)
- Test print quality with the preview function
- Include journal logo in background image for professional look
- Enable QR codes for modern, verifiable certificates
