## Version 1.0.3 - Font Size Setting Fix

**Release Date**: November 24, 2025

This patch release fixes a critical usability issue where the font size setting was not being applied to generated certificate PDFs. Users can now configure the font size in plugin settings and see the changes reflected in their certificates with proportional scaling across all text elements.

---

## 🔧 Critical Bug Fix

### Fixed: Font Size Setting Not Applied to PDF Content
- **Issue**: Users could configure fontSize in plugin settings, but it had **NO EFFECT** on generated certificates
- **Impact**: All certificates always used hardcoded font sizes regardless of user configuration
- **Root Cause**: PDF generation code explicitly set fixed font sizes that overrode the configured fontSize setting:
  - Header: Always 24pt (hardcoded)
  - Body: Always 14pt (hardcoded)
  - Footer: Always 10pt (hardcoded)
  - Certificate Code: Always 8pt (hardcoded)
  - QR Code Label: Always 6pt (hardcoded)
- **Solution**: Implemented proportional font size scaling based on the configured fontSize setting
- **Reported by**: Dr. Pavlo Nechypurenko

---

## ✨ Enhancement: Proportional Font Size Scaling

The fontSize setting now controls **all text elements** proportionally while maintaining proper visual hierarchy:

### Font Size Multipliers

| Text Element | Multiplier | Default (12pt) | Example (16pt) | Example (18pt) |
|--------------|-----------|----------------|----------------|----------------|
| **Header** | 2.0× | 24pt | 32pt | 36pt |
| **Body** | 1.167× | 14pt | 19pt | 21pt |
| **Footer** | 0.833× | 10pt | 13pt | 15pt |
| **Certificate Code** | 0.667× | 8pt | 11pt | 12pt |
| **QR Code Label** | 0.5× | 6pt | 8pt | 9pt |

### How It Works

**Before v1.0.3** (fontSize setting ignored):
```php
// User sets fontSize = 16 in settings
$fontSize = $this->getTemplateSetting('fontSize', 12); // Retrieved: 16

// But then hardcoded values were used:
$pdf->SetFont($fontFamily, 'B', 24);  // Header always 24pt
$pdf->SetFont($fontFamily, '', 14);   // Body always 14pt
$pdf->SetFont($fontFamily, 'I', 10);  // Footer always 10pt
$pdf->SetFont($fontFamily, '', 8);    // Code always 8pt
$pdf->SetFont($fontFamily, '', 6);    // QR label always 6pt
```

**After v1.0.3** (fontSize setting applied proportionally):
```php
// User sets fontSize = 16 in settings
$baseFontSize = $this->getTemplateSetting('fontSize', 12); // Retrieved: 16

// Calculate proportional sizes
$headerSize = round($baseFontSize * 2.0);    // 32pt
$bodySize = round($baseFontSize * 1.167);    // 19pt
$footerSize = round($baseFontSize * 0.833);  // 13pt
$codeSize = round($baseFontSize * 0.667);    // 11pt
$qrLabelSize = round($baseFontSize * 0.5);   // 8pt

// Apply calculated sizes
$pdf->SetFont($fontFamily, 'B', $headerSize);  // 32pt
$pdf->SetFont($fontFamily, '', $bodySize);     // 19pt
$pdf->SetFont($fontFamily, 'I', $footerSize);  // 13pt
$pdf->SetFont($fontFamily, '', $codeSize);     // 11pt
$pdf->SetFont($fontFamily, '', $qrLabelSize);  // 8pt
```

### Visual Hierarchy Maintained

The proportional multipliers ensure that text elements maintain their relative sizes:
- Header is always **2× the base size** (most prominent)
- Body is always **1.167× the base size** (readable content)
- Footer is always **0.833× the base size** (secondary info)
- Certificate Code is always **0.667× the base size** (small reference)
- QR Label is always **0.5× the base size** (minimal text)

This maintains professional appearance while giving users control over overall text sizing.

---

## 📝 Files Modified

### classes/CertificateGenerator.inc.php
**Lines 213-218**: Added proportional font size calculation
```php
// Get base font size from settings and calculate proportional sizes
$baseFontSize = $this->getTemplateSetting('fontSize', 12);
$headerSize = round($baseFontSize * 2.0);      // 2x base (default: 24)
$bodySize = round($baseFontSize * 1.167);      // 1.167x base (default: 14)
$footerSize = round($baseFontSize * 0.833);    // 0.833x base (default: 10)
$codeSize = round($baseFontSize * 0.667);      // 0.667x base (default: 8)
```

**Lines 226, 236, 247, 255**: Applied calculated sizes to text elements
```php
$pdf->SetFont($pdf->getFontFamily(), 'B', $headerSize);  // Instead of 24
$pdf->SetFont($pdf->getFontFamily(), '', $bodySize);     // Instead of 14
$pdf->SetFont($pdf->getFontFamily(), 'I', $footerSize);  // Instead of 10
$pdf->SetFont($pdf->getFontFamily(), '', $codeSize);     // Instead of 8
```

**Lines 300-301, 304**: Applied proportional size to QR code label
```php
$baseFontSize = $this->getTemplateSetting('fontSize', 12);
$qrLabelSize = round($baseFontSize * 0.5);     // 0.5x base (default: 6)
$pdf->SetFont($pdf->getFontFamily(), '', $qrLabelSize);
```

### tests/Unit/CertificateGeneratorTest.php
**Lines 190-226**: Added comprehensive test for proportional font sizes
```php
public function testProportionalFontSizes(): void
{
    $testCases = [
        [12, 24, 14, 10, 8, 6],   // Default configuration
        [16, 32, 19, 13, 11, 8],  // Larger font
        [10, 20, 12, 8, 7, 5],    // Smaller font
        [18, 36, 21, 15, 12, 9],  // Very large font
        [8, 16, 9, 7, 5, 4],      // Very small font
    ];
    // Validates all proportional calculations and visual hierarchy
}
```

---

## 🧪 Test Coverage

### New Test: `testProportionalFontSizes()`
- **Purpose**: Validates that font size calculations are correct for various base sizes
- **Test Cases**: 5 different base font sizes (8pt, 10pt, 12pt, 16pt, 18pt)
- **Validations**:
  - ✓ Header size calculated correctly (2.0× base)
  - ✓ Body size calculated correctly (1.167× base)
  - ✓ Footer size calculated correctly (0.833× base)
  - ✓ Certificate code size calculated correctly (0.667× base)
  - ✓ QR label size calculated correctly (0.5× base)
  - ✓ Visual hierarchy maintained (header > body > footer > code > QR)

---

## 🎯 Compatibility

**OJS Version Compatibility**:
- ✅ **OJS 3.3.x** - Fully compatible
- ✅ **OJS 3.4.x** - Fully compatible
- ✅ **OJS 3.5.x** - Fully compatible

**PHP Version Compatibility**:
- Minimum: PHP 7.3
- Recommended: PHP 8.0+
- Tested: PHP 7.3, 7.4, 8.0, 8.1, 8.2

**Backward Compatibility**:
- ✅ **100% Backward Compatible** - Default fontSize=12 produces identical output to v1.0.2
- ✅ **No Breaking Changes** - All existing certificates look the same
- ✅ **Safe Upgrade** - No database changes, no configuration changes required

---

## 📊 Technical Details

**Version**: 1.0.3.0
**Previous Version**: 1.0.2.0
**Release Type**: Patch (bug fix, no breaking changes)

**Changes Summary**:
- 1 commit (e58bc2a)
- 2 files modified
- 54 lines added (proportional font size logic and tests)
- 5 lines removed (hardcoded font size values)
- No database schema changes
- No configuration changes
- No API changes

**Migration Path**:
- From v1.0.2 → v1.0.3: Safe upgrade, no migration needed
- From v1.0.1 → v1.0.3: Safe upgrade, no migration needed
- From v1.0.0 → v1.0.3: Safe upgrade, no migration needed

---

## 📥 Installation & Upgrade

### For New Installations

```bash
cd /path/to/ojs/plugins/generic/
git clone https://github.com/ssemerikov/reviewerCertificate.git
cd reviewerCertificate
git checkout v1.0.3
chmod -R 755 .
```

Then enable in OJS admin: **Settings → Website → Plugins → Reviewer Certificate Plugin**

### Upgrading from 1.0.0, 1.0.1, or 1.0.2

**Safe to upgrade** - no database schema changes, no configuration changes required.

```bash
cd /path/to/ojs/plugins/generic/reviewerCertificate/
git fetch
git checkout v1.0.3
```

Then clear OJS cache:
```bash
php tools/upgrade.php check
```

Or simply refresh the plugins page in OJS admin interface.

---

## 🔍 What This Means for Users

### Before v1.0.3
1. User goes to plugin settings
2. User changes fontSize from 12 to 16
3. User clicks Save
4. **Nothing happens** - certificates still use size 12 fonts
5. User is confused why the setting doesn't work

### After v1.0.3
1. User goes to plugin settings
2. User changes fontSize from 12 to 16
3. User clicks Save
4. **All text scales proportionally**:
   - Header: 24pt → 32pt
   - Body: 14pt → 19pt
   - Footer: 10pt → 13pt
   - Code: 8pt → 11pt
   - QR: 6pt → 8pt
5. User sees the change immediately in generated certificates

### Use Cases

**Large Text for Accessibility**:
```
fontSize = 18
→ Header: 36pt, Body: 21pt, Footer: 15pt
Perfect for vision-impaired reviewers
```

**Compact Text for Dense Information**:
```
fontSize = 10
→ Header: 20pt, Body: 12pt, Footer: 8pt
Fits more content on one page
```

**Default Professional Look**:
```
fontSize = 12 (default)
→ Header: 24pt, Body: 14pt, Footer: 10pt
Unchanged from v1.0.2
```

---

## 📚 Documentation

- **Full Changelog**: See [CHANGELOG.md](CHANGELOG.md)
- **Installation Guide**: See [INSTALL.md](INSTALL.md)
- **User Documentation**: See [README.md](README.md)

---

## 🔗 Links

- **Repository**: https://github.com/ssemerikov/reviewerCertificate
- **Issues**: https://github.com/ssemerikov/reviewerCertificate/issues
- **Download Release**: https://github.com/ssemerikov/reviewerCertificate/releases/tag/v1.0.3

---

## 👏 Credits

**Author**: Serhiy O. Semerikov (Academy of Cognitive and Natural Sciences)
**Contact**: semerikov@gmail.com
**License**: GNU General Public License v3.0

**Development**: Built with Claude Code (Sonnet 4.5) by Anthropic

**Special Thanks**: Dr. Pavlo Nechypurenko for reporting this issue

---

## 📋 Upgrade Checklist

If upgrading from v1.0.0, v1.0.1, or v1.0.2:

- [ ] Backup your OJS database and files (recommended but not required)
- [ ] Note your current fontSize setting (if changed from default)
- [ ] Update plugin files (git checkout or download)
- [ ] Clear OJS cache
- [ ] Verify plugin loads in admin interface
- [ ] Check plugin settings are preserved
- [ ] **Test certificate generation** - fontSize setting now works!
- [ ] Adjust fontSize setting if needed (now that it actually works)

**Expected Result**: Plugin should load without errors, and fontSize setting should now control all text sizes proportionally

---

**Thank you** to Dr. Pavlo Nechypurenko for reporting this issue and helping improve the plugin! 🎓

**Note**: This release focuses solely on fixing the font size setting. All other features, settings, and functionality remain unchanged.
