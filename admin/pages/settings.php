<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Settings';
$settings  = getCompanySettings();
$tab       = $_GET['tab'] ?? 'branding';
$logoUrl   = companyLogoUrl($settings);

if ($tab === 'branding') {
    $pageStyles = '<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" rel="stylesheet">';
}

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header appointment-calendar-header">
        <div>
            <h2 class="saas-card-title">Company Settings</h2>
            <p class="saas-card-subtitle mb-0">Branding, email delivery, and payment configuration</p>
        </div>
    </div>
    <div class="card-body p-0">
        <ul class="nav nav-tabs settings-tabs px-3 pt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'branding' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=branding') ?>">Branding</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'email' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=email') ?>">Email / SMTP</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'payments' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=payments') ?>">Payments</a>
            </li>
        </ul>

        <form method="post" action="<?= url('actions/settings-action.php') ?>" enctype="multipart/form-data" class="p-4">
            <?= CSRF::field() ?>
            <input type="hidden" name="tab" value="<?= e($tab) ?>">

            <?php if ($tab === 'branding'): ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" required value="<?= e($settings['company_name']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Font Family</label>
                        <input type="text" name="font_family" class="form-control" value="<?= e($settings['font_family'] ?? 'Montserrat') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Primary Color</label>
                        <input type="color" name="primary_color" class="form-control form-control-color w-100" value="<?= e($settings['primary_color']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Secondary Color</label>
                        <input type="color" name="secondary_color" class="form-control form-control-color w-100" value="<?= e($settings['secondary_color']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Accent Color</label>
                        <input type="color" name="dark_accent" class="form-control form-control-color w-100" value="<?= e($settings['dark_accent'] ?? '#000000') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Office Email</label>
                        <input type="email" name="office_email" class="form-control" value="<?= e($settings['office_email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Office Phone</label>
                        <input type="text" name="office_phone" class="form-control" value="<?= e($settings['office_phone'] ?? '') ?>" placeholder="+1 (555) 123-4567">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Business Hours</label>
                        <textarea name="business_hours" class="form-control" rows="3" placeholder="Monday – Friday: 9:00 AM – 5:00 PM"><?= e($settings['business_hours'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= e($settings['address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($settings['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Company Logo</label>
                        <p class="text-muted small mb-3">Upload a square image (or crop one) so it fills the sidebar and login areas.</p>
                        <div class="logo-branding-panel">
                            <div class="logo-upload-toolbar">
                                <input type="file" id="logoFileInput" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp,.svg,image/*">
                                <?php if ($logoUrl): ?>
                                    <button type="button" class="btn btn-soft btn-sm" id="logoEditCurrentBtn">
                                        <i class="bi bi-crop"></i> Edit logo
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="logo-placement-preview">
                                <p class="logo-placement-preview-title">Where your logo appears</p>
                                <div class="logo-placement-grid">
                                    <div class="logo-placement-card">
                                        <p class="logo-placement-name">Sidebar</p>
                                        <div class="logo-placement-stage logo-placement-stage--sidebar">
                                            <div class="logo-placement-mock-bar">
                                                <div class="logo-frame-preview logo-frame-preview--sidebar" id="logoPreviewSidebar">
                                                    <?php if ($logoUrl): ?>
                                                        <img src="<?= e($logoUrl) ?>" alt="">
                                                    <?php else: ?>
                                                        <?= renderCompanyLogo('sidebar', $settings, 'admin') ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="logo-placement-mock-copy">
                                                    <span class="logo-placement-mock-title"><?= e(companyBrandName($settings)) ?></span>
                                                    <span class="logo-placement-mock-tag">Admin</span>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="logo-placement-meta">38 × 38 px</span>
                                    </div>
                                    <div class="logo-placement-card">
                                        <p class="logo-placement-name">Login page</p>
                                        <div class="logo-placement-stage logo-placement-stage--auth">
                                            <div class="logo-frame-preview logo-frame-preview--auth" id="logoPreviewAuth">
                                                <?php if ($logoUrl): ?>
                                                    <img src="<?= e($logoUrl) ?>" alt="">
                                                <?php else: ?>
                                                    <span class="logo-frame-preview-empty" aria-hidden="true"><i class="bi bi-image"></i></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="logo-placement-meta">64 × 64 px</span>
                                    </div>
                                </div>
                                <?php if (!$logoUrl): ?>
                                    <p class="logo-placement-footnote mb-0">Until you upload a logo, the sidebar shows a default icon.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'email'): ?>
                <div class="alert alert-info border-0 small">
                    Configure SMTP to send quotation, login, and appointment emails. Leave host empty to use PHP <code>mail()</code> (logged in debug mode).
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?= e($settings['smtp_host'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= (int) ($settings['smtp_port'] ?? 587) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">SMTP Username</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= e($settings['smtp_username'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">SMTP Password</label>
                        <input type="password" name="smtp_password" class="form-control" placeholder="<?= !empty($settings['smtp_password']) ? '••••••••' : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Encryption</label>
                        <select name="smtp_encryption" class="form-select">
                            <?php foreach (['tls', 'ssl', 'none'] as $enc): ?>
                                <option value="<?= $enc ?>" <?= ($settings['smtp_encryption'] ?? 'tls') === $enc ? 'selected' : '' ?>><?= strtoupper($enc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info border-0 small mb-3">
                    Add your Stripe publishable and secret keys to enable client online checkout. Manual payments can still be recorded from the Payments page or case workspace.
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Stripe Publishable Key</label>
                        <input type="text" name="stripe_public_key" class="form-control" value="<?= e($settings['stripe_public_key'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Stripe Secret Key</label>
                        <input type="password" name="stripe_secret_key" class="form-control" placeholder="<?= !empty($settings['stripe_secret_key']) ? '••••••••' : '' ?>">
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Settings</button>
            </div>
        </form>
    </div>
</div>

<?php if ($tab === 'branding'): ?>
<div class="modal fade" id="logoCropModal" tabindex="-1" aria-labelledby="logoCropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoCropModalLabel">Adjust company logo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="logo-crop-aspect-btns mb-3" role="group" aria-label="Crop aspect ratio">
                    <button type="button" class="btn btn-soft btn-sm active" data-logo-aspect="1">Square (recommended)</button>
                    <button type="button" class="btn btn-soft btn-sm" data-logo-aspect="0">Free crop</button>
                </div>
                <div class="logo-crop-workspace">
                    <div class="logo-crop-stage">
                        <img src="" alt="" id="logoCropImage" class="logo-crop-image">
                    </div>
                    <div class="logo-crop-previews">
                        <p class="logo-crop-previews-title">Live preview</p>
                        <div class="logo-crop-preview-stack">
                            <div class="logo-placement-stage logo-placement-stage--sidebar logo-crop-live-stage">
                                <div class="logo-placement-mock-bar">
                                    <div class="logo-frame-preview logo-frame-preview--sidebar logo-crop-live" id="logoCropLiveSidebar"></div>
                                    <div class="logo-placement-mock-copy">
                                        <span class="logo-placement-mock-title">Sidebar</span>
                                    </div>
                                </div>
                            </div>
                            <div class="logo-placement-stage logo-placement-stage--auth logo-crop-live-stage">
                                <div class="logo-frame-preview logo-frame-preview--auth logo-crop-live" id="logoCropLiveAuth"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-0 mt-3">Drag to reposition, use the handles to resize the crop area, or zoom with the mouse wheel.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="logoCropApplyBtn">
                    <i class="bi bi-check-lg"></i> Apply logo
                </button>
            </div>
        </div>
    </div>
</div>
<?php
$pageScripts = '<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
(function() {
    var fileInput = document.getElementById("logoFileInput");
    var editBtn = document.getElementById("logoEditCurrentBtn");
    var modalEl = document.getElementById("logoCropModal");
    var cropImg = document.getElementById("logoCropImage");
    var applyBtn = document.getElementById("logoCropApplyBtn");
    var form = document.querySelector("form[enctype]");
    var previewSidebar = document.getElementById("logoPreviewSidebar");
    var previewAuth = document.getElementById("logoPreviewAuth");
    var liveSidebar = document.getElementById("logoCropLiveSidebar");
    var liveAuth = document.getElementById("logoCropLiveAuth");
    var currentLogoUrl = ' . json_encode($logoUrl ?: '') . ';

    if (!fileInput || !modalEl || !cropImg || typeof Cropper === "undefined") {
        return;
    }

    var modal = new bootstrap.Modal(modalEl);
    var cropper = null;
    var croppedFile = null;
    var pendingCropFile = false;
    var aspectRatio = 1;

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    function setPreviewImage(container, dataUrl) {
        if (!container) return;
        container.innerHTML = "";
        if (!dataUrl) return;
        var img = document.createElement("img");
        img.src = dataUrl;
        img.alt = "Logo preview";
        container.appendChild(img);
    }

    function updateLivePreviews() {
        if (!cropper) return;
        var square = cropper.getCroppedCanvas({ width: 128, height: 128, imageSmoothingQuality: "high" });
        var auth = cropper.getCroppedCanvas({ width: 128, height: 128, imageSmoothingQuality: "high" });
        if (square) setPreviewImage(liveSidebar, square.toDataURL("image/png"));
        if (auth) setPreviewImage(liveAuth, auth.toDataURL("image/png"));
    }

    function initCropper(src) {
        destroyCropper();
        cropImg.src = src;
        cropImg.onload = function() {
            cropper = new Cropper(cropImg, {
                aspectRatio: aspectRatio || NaN,
                viewMode: 1,
                dragMode: "move",
                autoCropArea: 0.9,
                responsive: true,
                background: false,
                crop: updateLivePreviews
            });
            updateLivePreviews();
        };
    }

    function openCropperFromFile(file) {
        if (!file) return;
        if (file.type === "image/svg+xml" || (file.name && file.name.toLowerCase().endsWith(".svg"))) {
            alert("SVG files are uploaded as-is. For crop and frame preview, use PNG or JPG.");
            return;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            modal.show();
            initCropper(e.target.result);
        };
        reader.readAsDataURL(file);
    }

    function openCropperFromUrl(url) {
        if (!url) return;
        modal.show();
        initCropper(url);
    }

    fileInput.addEventListener("change", function() {
        croppedFile = null;
        pendingCropFile = !!(fileInput.files && fileInput.files[0]);
        if (pendingCropFile) {
            openCropperFromFile(fileInput.files[0]);
        }
    });

    if (editBtn) {
        editBtn.addEventListener("click", function() {
            fileInput.value = "";
            openCropperFromUrl(currentLogoUrl);
        });
    }

    document.querySelectorAll("[data-logo-aspect]").forEach(function(btn) {
        btn.addEventListener("click", function() {
            document.querySelectorAll("[data-logo-aspect]").forEach(function(b) { b.classList.remove("active"); });
            btn.classList.add("active");
            var val = parseFloat(btn.getAttribute("data-logo-aspect"));
            aspectRatio = val > 0 ? val : NaN;
            if (cropper) {
                cropper.setAspectRatio(aspectRatio);
                updateLivePreviews();
            }
        });
    });

    applyBtn.addEventListener("click", function() {
        if (!cropper) return;
        var canvas = cropper.getCroppedCanvas({
            width: 512,
            height: 512,
            imageSmoothingQuality: "high"
        });
        if (!canvas) return;

        canvas.toBlob(function(blob) {
            if (!blob) return;
            croppedFile = new File([blob], "logo.png", { type: "image/png" });
            pendingCropFile = false;
            var dataUrl = canvas.toDataURL("image/png");
            setPreviewImage(previewSidebar, dataUrl);
            setPreviewImage(previewAuth, dataUrl);
            var dt = new DataTransfer();
            dt.items.add(croppedFile);
            fileInput.files = dt.files;
            modal.hide();
        }, "image/png", 0.92);
    });

    modalEl.addEventListener("hidden.bs.modal", function() {
        destroyCropper();
        cropImg.removeAttribute("src");
        if (pendingCropFile && !croppedFile) {
            fileInput.value = "";
        }
        pendingCropFile = false;
    });

    if (form) {
        form.addEventListener("submit", function() {
            if (croppedFile) {
                var dt = new DataTransfer();
                dt.items.add(croppedFile);
                fileInput.files = dt.files;
            }
        });
    }
})();
</script>';
endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
