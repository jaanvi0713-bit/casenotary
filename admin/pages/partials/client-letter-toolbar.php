<?php
/**
 * Standard client letter action toolbar (same layout for every company).
 *
 * @var int $caseId
 * @var bool $hasGeneratedDraft
 * @var bool $currentLetterPublished
 * @var string|null $clientLetterPath
 * @var bool $letterIsPdf
 */
$letterDownloadRel = $clientLetterPath ?? ('cases/' . $caseId . '/generated/client_letter.' . ($letterIsPdf ? 'pdf' : 'html'));
$generateLabel = $hasGeneratedDraft ? 'Regenerate' : 'Generate letter';
?>
<div class="client-letter-actions d-flex flex-wrap gap-2 mb-3" role="toolbar" aria-label="Client letter actions">
    <button type="button" class="btn btn-soft btn-sm case-action-btn client-letter-action-btn" id="clientLetterPreviewBtn">
        <i class="bi bi-eye" aria-hidden="true"></i><span>Preview letter</span>
    </button>
    <a href="<?= url('actions/document-download.php?path=' . urlencode($letterDownloadRel)) ?>"
       class="btn btn-soft btn-sm case-action-btn client-letter-action-btn"
       target="_blank"
       rel="noopener">
        <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i><span>Download</span>
    </a>
    <button type="submit" data-letter-action="generate_client_letter" class="btn btn-soft btn-sm case-action-btn client-letter-action-btn">
        <i class="bi bi-file-earmark-plus" aria-hidden="true"></i><span><?= e($generateLabel) ?></span>
    </button>
    <button type="submit" data-letter-action="save_client_letter_record" class="btn btn-soft btn-sm case-action-btn client-letter-action-btn">
        <i class="bi bi-save" aria-hidden="true"></i><span>Save to client record</span>
    </button>
    <?php if ($currentLetterPublished): ?>
        <button type="submit" data-letter-action="unpublish_client_letter" class="btn btn-soft btn-sm case-action-btn client-letter-action-btn">
            <i class="bi bi-eye-slash" aria-hidden="true"></i><span>Unpublish from client portal</span>
        </button>
    <?php else: ?>
        <button type="submit" data-letter-action="publish_client_letter" class="btn btn-soft btn-sm case-action-btn client-letter-action-btn">
            <i class="bi bi-globe" aria-hidden="true"></i><span>Publish to client portal</span>
        </button>
    <?php endif; ?>
    <button type="submit" data-letter-action="send_client_letter" class="btn btn-soft btn-sm case-action-btn client-letter-action-btn">
        <i class="bi bi-envelope" aria-hidden="true"></i><span>Email to client</span>
    </button>
</div>
