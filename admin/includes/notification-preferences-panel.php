<?php
/** @var array<string, array{in_app: bool, email: bool}> $notificationPrefs */
/** @var string $preferencesAction */
/** @var bool $preferencesReady */
/** @var bool $notificationPrefsEmbedded */
$notificationPrefsEmbedded = $notificationPrefsEmbedded ?? false;

if (!$notificationPrefsEmbedded): ?>
<div class="saas-card notification-preferences-card mb-4">
    <div class="saas-card-header">
        <div>
            <h2 class="saas-card-title">Notification preferences</h2>
            <p class="saas-card-subtitle mb-0">Choose which alerts you receive in the app and by email</p>
        </div>
    </div>
    <div class="card-body p-4">
<?php endif; ?>

        <?php if (!$preferencesReady): ?>
            <div class="alert alert-warning border-0 mb-0">
                Notification preferences are not installed. Run
                <code>php admin/sql/migrate_notification_preferences.php</code>
                from the project root.
            </div>
        <?php else: ?>
            <form method="post" action="<?= e($preferencesAction) ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="save_preferences">
                <div class="table-responsive">
                    <table class="table table-sm notification-preferences-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-center">In-app</th>
                                <th class="text-center">Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (NotificationPreferenceService::TYPES as $prefType): ?>
                                <?php $pref = $notificationPrefs[$prefType] ?? ['in_app' => true, 'email' => false]; ?>
                                <tr>
                                    <td>
                                        <div class="notification-pref-label">
                                            <i class="bi <?= notificationIcon($prefType) ?> me-2"></i>
                                            <?= e(NotificationPreferenceService::typeLabel($prefType)) ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            name="preferences[<?= e($prefType) ?>][in_app]"
                                            value="1"
                                            <?= !empty($pref['in_app']) ? 'checked' : '' ?>
                                        >
                                    </td>
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            name="preferences[<?= e($prefType) ?>][email]"
                                            value="1"
                                            <?= !empty($pref['email']) ? 'checked' : '' ?>
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="form-text mt-3 mb-3">Email alerts use your workspace SMTP settings. You can disable in-app alerts and keep email only.</p>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i> Save preferences
                </button>
            </form>
        <?php endif; ?>

<?php if (!$notificationPrefsEmbedded): ?>
    </div>
</div>
<?php endif; ?>
