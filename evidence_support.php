<?php
const EVIDENCE_MAX_FILES = 4;
const EVIDENCE_MAX_FILE_BYTES = 20 * 1024 * 1024;
const EVIDENCE_MAX_TOTAL_BYTES = 50 * 1024 * 1024;
const EVIDENCE_DIRECTORY = __DIR__ . '/storage/evidence';

function evidenceEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Validate the entire selection before moving files or saving a complaint. */
function validateEvidenceUploads(array $uploads): array
{
    if (!$uploads) return [];
    if (!isset($uploads['name'], $uploads['tmp_name'], $uploads['error']) || !is_array($uploads['name']) || !is_array($uploads['error']) || !is_array($uploads['tmp_name'])) {
        throw new InvalidArgumentException('Please select your evidence files again.');
    }
    if (count($uploads['name']) > EVIDENCE_MAX_FILES) {
        throw new InvalidArgumentException('You can attach up to 4 photos or videos.');
    }
    $allowed = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'], 'video/mp4' => ['mp4'], 'video/webm' => ['webm'], 'video/quicktime' => ['mov']];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $files = [];
    $total = 0;
    foreach ($uploads['name'] as $index => $name) {
        $error = (int) ($uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE ? 'One of your files exceeds the upload limit. Each file must be 20 MB or smaller.' : 'One of your uploads did not finish. Please select the files again.');
        }
        $tmp = $uploads['tmp_name'][$index] ?? '';
        if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new InvalidArgumentException('Invalid evidence upload.');
        $size = filesize($tmp);
        if ($size === false || $size === 0 || $size > EVIDENCE_MAX_FILE_BYTES) throw new InvalidArgumentException('Each evidence file must be nonempty and no larger than 20 MB.');
        $total += $size;
        if ($total > EVIDENCE_MAX_TOTAL_BYTES) throw new InvalidArgumentException('Your combined evidence files must be 50 MB or smaller.');
        $mime = $finfo->file($tmp);
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        if (!isset($allowed[$mime]) || !in_array($extension, $allowed[$mime], true)) {
            throw new InvalidArgumentException('Use JPG, PNG, WebP, MP4, WebM, or MOV files. Other file types are not accepted.');
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($tmp) === false) throw new InvalidArgumentException('One of your images is not a valid picture.');
        $safeName = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', (string) $name)));
        $files[] = ['tmp_name' => $tmp, 'original_name' => mb_substr($safeName ?: 'Evidence.' . $extension, 0, 200), 'mime_type' => $mime, 'size' => $size];
    }
    return $files;
}

/** Keep $movedPaths outside this function so partial failures can be cleaned up. */
function saveComplaintEvidence(PDO $pdo, int $complaintId, array $files, array &$movedPaths): void
{
    if (!$files) return;
    if (!is_dir(EVIDENCE_DIRECTORY) && !mkdir(EVIDENCE_DIRECTORY, 0700, true)) throw new RuntimeException('Evidence storage is unavailable.');
    $insert = $pdo->prepare('INSERT INTO complaint_evidence (complaint_id, path, original_name, mime_type, size) VALUES (?, ?, ?, ?, ?)');
    foreach ($files as $file) {
        // Opaque .bin names prevent the uploaded content from becoming executable.
        $storedName = bin2hex(random_bytes(24)) . '.bin';
        $path = EVIDENCE_DIRECTORY . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('Unable to store evidence.');
        $movedPaths[] = $path;
        $insert->execute([$complaintId, 'evidence/' . $storedName, $file['original_name'], $file['mime_type'], $file['size']]);
    }
}

function removeFailedEvidence(array $paths): void
{
    foreach ($paths as $path) {
        if (dirname($path) === EVIDENCE_DIRECTORY && is_file($path)) unlink($path);
    }
}

function renderEvidenceGallery(array $files, string $prefix = ''): void
{
    if (!$files) return;
    echo '<div class="evidence-gallery">';
    foreach ($files as $file) {
        $url = $prefix . 'evidence.php?id=' . (int) $file['id'];
        $name = evidenceEscape($file['original_name']);
        echo '<figure class="evidence-tile">';
        if (str_starts_with($file['mime_type'], 'image/')) {
            echo '<a href="' . $url . '" target="_blank" rel="noopener" aria-label="Open photo: ' . $name . '"><img src="' . $url . '" alt="' . $name . '" loading="lazy"></a>';
        } else {
            echo '<video controls preload="metadata" playsinline aria-label="' . $name . '"><source src="' . $url . '" type="' . evidenceEscape($file['mime_type']) . '">Your browser cannot play this video. Use the download link.</video>';
        }
        echo '<figcaption><span title="' . $name . '">' . $name . '</span><a href="' . $url . '&download=1">Download <span class="visually-hidden">' . $name . '</span></a><small>' . (($file['purpose'] ?? '') === 'resolution' ? 'Completion evidence · ' : '') . number_format($file['size'] / 1024 / 1024, 2) . ' MB</small></figcaption></figure>';
    }
    echo '</div>';
}
