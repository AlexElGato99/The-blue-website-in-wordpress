# Taramide style audit — 6 November 2026

Reference: https://taramide.com/
Preview: http://localhost/wordpress/

The comparison covered the homepage, catalogue, events, blog, contact, product details, article details and shared footer. Layout changes were applied to all seven language versions while retaining Arabic RTL and the existing rebuilt headers.

Changes made in Elementor:
- Installed the reference Fraunces font as a locally hosted Elementor custom font.
- Matched hero typography, line breaks, button layout, statistics and responsive alignment.
- Corrected content widths, section spacing, sidebar layouts, wave separators and small labels.
- Changed homepage products to four desktop columns, two tablet columns and one mobile column; catalogue uses three desktop columns.
- Replaced process photos with the existing matching line illustrations; original photos appear on hover.
- Rebuilt certifications as four compact logo panels with descriptions available on hover.
- Matched event card proportions and badges, two-column FAQ, gallery rail and three-column partner section.
- Recreated the story location card, forest contact section, cream form card and consistent translated footers.
- Rebuilt product information into four native Elementor accordion panels per language. Original post content remains intact; panel text is supplied from copied product metadata.
- Matched centered article headers and larger editorial images.
- Removed stray text from malformed SVG assets and corrected responsive CSS and overflow.

Verification:
- 35 main pages across seven languages checked at 390, 768 and 1440 px (105 checks).
- Homepage checks additionally cover 320 px and Arabic RTL.
- Four product-information panels opened and validated in each language; representative product and article layouts checked at desktop/mobile widths.
- FAQ panels open and close; catalogue category filter selects and updates displayed products.
- Content images, overflow, translated labels and native template data were checked.
- Forms were inspected without sending messages.

Backups, scripts, visual comparisons, JSON reports and the final template export are in D:\taramide-style-audit. The original layouts are in before.json; the final layouts are in final-elementor.json. The reference font is available under Elementor Custom Fonts as Fraunces.
