<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Mrynarzewski\InpostApi\ShipmentWorkflow;

$workflow = new ShipmentWorkflow(dirname(__DIR__));
$presentation = $workflow->getPresentationData();

?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($presentation['project']['name']) ?> Showcase</title>
    <meta name="description" content="<?= htmlspecialchars($presentation['project']['summary']) ?>">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="page-shell">
        <header class="hero">
            <div class="hero-copy reveal">
                <p class="eyebrow">Portfolio Case Study</p>
                <h1><?= htmlspecialchars($presentation['project']['name']) ?></h1>
                <p class="lede"><?= htmlspecialchars($presentation['project']['subtitle']) ?></p>
                <p class="summary"><?= htmlspecialchars($presentation['project']['summary']) ?></p>

                <div class="hero-actions">
                    <button class="action action-primary" data-run-mode="simulate">Uruchom demo</button>
                    <button class="action action-secondary" data-run-mode="live">Uruchom live z sandboxa</button>
                </div>
            </div>

            <aside class="hero-panel reveal">
                <p class="panel-label">Dlaczego ta warstwa istnieje</p>
                <p class="panel-copy">Projekt zaczal sie jako pojedynczy skrypt CLI. Teraz da sie go pokazac jako czytelny przeplyw integracyjny: od request payload po dispatch order.</p>
                <dl class="metric-grid">
                    <?php foreach ($presentation['metrics'] as $metric): ?>
                        <div class="metric">
                            <dt><?= htmlspecialchars($metric['label']) ?></dt>
                            <dd><?= htmlspecialchars($metric['value']) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </aside>
        </header>

        <main class="layout">
            <section class="story">
                <div class="section-head reveal">
                    <p class="eyebrow">Co pokazuje ekran</p>
                    <h2>Techniczna integracja ubrana w czytelny narrative</h2>
                </div>

                <div class="highlight-grid">
                    <?php foreach ($presentation['highlights'] as $highlight): ?>
                        <article class="story-card reveal">
                            <h3><?= htmlspecialchars($highlight['title']) ?></h3>
                            <p><?= htmlspecialchars($highlight['copy']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="sequence reveal">
                    <?php foreach ($presentation['sequence'] as $step): ?>
                        <article class="sequence-step">
                            <p class="sequence-index"><?= htmlspecialchars($step['step']) ?></p>
                            <h3><?= htmlspecialchars($step['title']) ?></h3>
                            <p><?= htmlspecialchars($step['copy']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="console reveal">
                <div class="console-head">
                    <div>
                        <p class="eyebrow">Interactive Runner</p>
                        <h2>Podglad payloadu i odpowiedzi API</h2>
                    </div>
                    <p class="console-badge" id="run-mode-label">Tryb demo</p>
                </div>

                <form id="demo-form" class="demo-form">
                    <div class="field-group">
                        <label for="organization_id">Organization ID</label>
                        <input id="organization_id" name="organization_id" value="<?= htmlspecialchars($presentation['defaults']['organization_id']) ?>">
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label for="receiver_first_name">Odbiorca</label>
                            <input id="receiver_first_name" name="receiver_first_name" value="<?= htmlspecialchars($presentation['defaults']['shipment']['receiver']['first_name']) ?>">
                        </div>
                        <div class="field-group">
                            <label for="receiver_last_name">Nazwisko</label>
                            <input id="receiver_last_name" name="receiver_last_name" value="<?= htmlspecialchars($presentation['defaults']['shipment']['receiver']['last_name']) ?>">
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label for="receiver_city">Miasto odbiorcy</label>
                            <input id="receiver_city" name="receiver_city" value="<?= htmlspecialchars($presentation['defaults']['shipment']['receiver']['address']['city']) ?>">
                        </div>
                        <div class="field-group">
                            <label for="receiver_post_code">Kod pocztowy odbiorcy</label>
                            <input
                                id="receiver_post_code"
                                name="receiver_post_code"
                                inputmode="numeric"
                                pattern="\d{2}-?\d{3}"
                                placeholder="00-000"
                                value="<?= htmlspecialchars($presentation['defaults']['shipment']['receiver']['address']['post_code']) ?>"
                            >
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label for="sender_city">Miasto nadawcy</label>
                            <input id="sender_city" name="sender_city" value="<?= htmlspecialchars($presentation['defaults']['shipment']['sender']['address']['city']) ?>">
                        </div>
                        <div class="field-group">
                            <label for="sender_post_code">Kod pocztowy nadawcy</label>
                            <input
                                id="sender_post_code"
                                name="sender_post_code"
                                inputmode="numeric"
                                pattern="\d{2}-?\d{3}"
                                placeholder="00-000"
                                value="<?= htmlspecialchars($presentation['defaults']['shipment']['sender']['address']['post_code']) ?>"
                            >
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label for="dispatch_city">Miasto odbioru</label>
                            <input id="dispatch_city" name="dispatch_city" value="<?= htmlspecialchars($presentation['defaults']['dispatch']['address']['city']) ?>">
                        </div>
                        <div class="field-group">
                            <label for="dispatch_post_code">Kod pocztowy odbioru</label>
                            <input
                                id="dispatch_post_code"
                                name="dispatch_post_code"
                                inputmode="numeric"
                                pattern="\d{2}-?\d{3}"
                                placeholder="00-000"
                                value="<?= htmlspecialchars($presentation['defaults']['dispatch']['address']['post_code']) ?>"
                            >
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label for="parcel_weight">Waga (kg)</label>
                            <input id="parcel_weight" name="parcel_weight" value="<?= htmlspecialchars($presentation['defaults']['shipment']['parcels'][0]['weight']['amount']) ?>">
                        </div>
                        <div class="field-group">
                            <label for="dispatch_comment">Komentarz dla kuriera</label>
                            <input id="dispatch_comment" name="dispatch_comment" value="<?= htmlspecialchars($presentation['defaults']['dispatch']['comment']) ?>">
                        </div>
                    </div>

                    <p class="form-note">
                        Kody pocztowe akceptuja format <strong>00-000</strong>. Wpisane <strong>12345</strong> lub <strong>12 345</strong> zostanie automatycznie znormalizowane do <strong>12-345</strong>.
                    </p>
                </form>

                <div class="runner-actions">
                    <button class="action action-primary" data-run-mode="simulate" data-form-submit>Odegraj demo case</button>
                    <button class="action action-secondary" data-run-mode="live" data-form-submit>Wyslij do sandboxa</button>
                </div>

                <div class="output-grid">
                    <div class="timeline-panel">
                        <div class="panel-head">
                            <h3>Timeline</h3>
                            <p id="run-status">Gotowe do uruchomienia</p>
                        </div>
                        <div id="timeline" class="timeline"></div>
                    </div>

                    <div class="payload-panel">
                        <div class="panel-head">
                            <h3>Payload / response</h3>
                            <p>Wybrany krok pokazuje request i odpowiedz w JSON.</p>
                        </div>
                        <pre id="payload-view" class="payload-view"><code>Kliknij "Uruchom demo", aby zobaczyc przeplyw.</code></pre>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        window.focusGardenPresentation = <?= json_encode($presentation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>;
    </script>
    <script src="app.js"></script>
</body>
</html>
