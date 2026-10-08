<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/ProcessNormalizer.php';
require_once __DIR__ . '/../src/ProcessRepository.php';
require_once __DIR__ . '/../src/ProcessService.php';
require_once __DIR__ . '/../src/RequestHandler.php';

const DATA_FILE = __DIR__ . '/../data/processes.json';

session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Strict',
    'path' => '/',
]);
session_start();

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$repository = new ProcessRepository(DATA_FILE);
$service = new ProcessService($repository);
$requestHandler = new RequestHandler($repository, $service);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Ungültige Anfrage.');
        }

        if (!hash_equals($_SESSION['csrf'], (string)($payload['csrf'] ?? ''))) {
            throw new InvalidArgumentException('Sitzung abgelaufen.');
        }

        echo json_encode($requestHandler->handle($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

try {
    $data = $repository->load();
} catch (Throwable $e) {
    $data = ProcessNormalizer::defaultConfig();
    $error = $e->getMessage();
}

$page = strtolower((string)($_GET['page'] ?? 'library'));
$id = trim((string)($_GET['id'] ?? ''));
$current = null;
if ($id !== '') {
    foreach ($data['processes'] as $process) {
        if (($process['id'] ?? '') === $id) {
            $current = $process;
            break;
        }
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Znuny Prozessdokumentation</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body data-page="<?= h($page) ?>">
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">Znuny Prozessdokumentation</div>
            <nav class="mainnav">
                <a href="/?page=library">Prozesse</a>
                <a href="/?page=admin">Verwaltung</a>
                <a href="/?page=config">Ticketfelder</a>
                <a href="/?page=editor">Editor</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if (isset($error) && $error !== ''): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($page === 'library'): ?>
            <section class="page-head">
                <div>
                    <h1>Prozesse</h1>
                    <p>Dokumentation und Ablaufübersicht</p>
                </div>
                <a class="button primary" href="/?page=editor">Neuer Prozess</a>
            </section>
            <div class="card-grid">
                <?php if (!$data['processes']): ?>
                    <div class="empty-state">Keine Prozesse vorhanden.</div>
                <?php endif; ?>
                <?php foreach ($data['processes'] as $process): ?>
                    <article class="card">
                        <h2><?= h($process['name'] ?? 'Unbenannter Prozess') ?></h2>
                        <p><?= h($process['description'] ?? 'Keine Beschreibung.') ?></p>
                        <div class="meta-row">
                            <span><?= count($process['steps'] ?? []) ?> Schritte</span>
                            <span><?= !empty($process['published']) ? 'veröffentlicht' : 'Entwurf' ?></span>
                        </div>
                        <div class="card-actions">
                            <a class="button primary" href="/?page=view&id=<?= h((string)($process['id'] ?? '')) ?>">Öffnen</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php elseif ($page === 'admin'): ?>
            <section class="page-head">
                <div>
                    <h1>Verwaltung</h1>
                    <p>Import, Export und Prozessverwaltung</p>
                </div>
                <div class="action-row">
                    <button id="export-all" type="button" class="button">Export</button>
                    <label class="button" for="import-json">Import</label>
                    <input id="import-json" type="file" accept="application/json" class="hidden-input">
                    <a class="button primary" href="/?page=editor">Neuer Prozess</a>
                </div>
            </section>

            <div id="admin-list" class="list-box"></div>

        <?php elseif ($page === 'config'): ?>
            <section class="page-head">
                <div>
                    <h1>Ticketfelder</h1>
                    <p>Standardfelder konfigurieren</p>
                </div>
                <button id="save-config" type="button" class="button primary">Speichern</button>
            </section>
            <div id="config-list" class="config-box"></div>

        <?php elseif ($page === 'editor'): ?>
            <?php $process = $current ?? ['id' => '', 'name' => '', 'description' => '', 'yamlName' => '', 'published' => true, 'variables' => [], 'steps' => [[ 'id' => 'step-1', 'action' => 'ticket-create', 'subject' => '', 'body' => '', 'fields' => [], 'links' => [], 'meta' => ['system' => '', 'url' => '', 'address' => '', 'tasks' => [], 'condition' => ['variable' => '', 'operator' => 'always', 'value' => '']]]]; ?>
            <section class="page-head">
                <div>
                    <h1>Editor</h1>
                    <p>Prozess anlegen oder bearbeiten</p>
                </div>
                <div class="action-row">
                    <a class="button" href="/?page=admin">Zur Verwaltung</a>
                    <button id="save-process" type="button" class="button primary">Prozess speichern</button>
                </div>
            </section>
            <div class="editor-shell">
                <div class="form-grid wide">
                    <label>
                        <span>Prozessname</span>
                        <input id="process-name" type="text" value="<?= h($process['name'] ?? '') ?>">
                    </label>
                    <label>
                        <span>YAML-Dateiname</span>
                        <input id="process-yaml" type="text" value="<?= h($process['yamlName'] ?? '') ?>">
                    </label>
                    <label>
                        <span>Kurzbeschreibung</span>
                        <textarea id="process-description"><?= h($process['description'] ?? '') ?></textarea>
                    </label>
                    <label>
                        <span>Veröffentlicht</span>
                        <select id="process-published">
                            <option value="1" <?= (!empty($process['published'])) ? 'selected' : '' ?>>Ja</option>
                            <option value="0" <?= (empty($process['published'])) ? 'selected' : '' ?>>Nein</option>
                        </select>
                    </label>
                </div>
                <div id="editor-steps" class="steps-panel"></div>
                <button id="add-step" type="button" class="button primary round-btn">+</button>
            </div>

        <?php elseif ($page === 'view'): ?>
            <?php if (!$current): ?>
                <div class="empty-state">Prozess nicht gefunden.</div>
            <?php else: ?>
                <section class="page-head">
                    <div>
                        <h1><?= h($current['name'] ?? 'Prozess') ?></h1>
                        <p><?= h($current['description'] ?? '') ?></p>
                    </div>
                    <a class="button" href="/?page=library">Zurück</a>
                </section>
                <div id="view-steps" class="steps-panel"></div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">Seite nicht gefunden.</div>
        <?php endif; ?>
    </main>

    <div id="toast" class="toast" aria-live="polite"></div>

    <script>
        window.APP = {
            csrf: <?= json_encode($_SESSION['csrf']) ?>,
            config: <?= json_encode($data['configuration'] ?? ['fields' => [], 'linkTypes' => ['Normal']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            processes: <?= json_encode($data['processes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            page: <?= json_encode($page) ?>,
            current: <?= json_encode($current ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>
