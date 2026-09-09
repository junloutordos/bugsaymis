{{--
    Inline (non-fixed) footer, used only inside the accreditation package
    bundle (_bundle.blade.php) — one combined mPDF document holds every
    form's content back to back, so a `position:fixed` footer per section
    would repeat onto every subsequent page instead of staying with its own
    form. Standalone single-form PDFs get the real fixed-to-page-bottom
    footer from _layout.blade.php instead.
--}}
<div class="form-footer-inline">{{ $formCode ?? '' }} | Generated {{ now()->format('m/d/Y') }} | Controlled when verified in Atlas</div>
