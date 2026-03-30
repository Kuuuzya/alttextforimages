// ── Toggle API key ────────────────────────────────────────────────────────────
function iagToggleKey() {
    var input = document.getElementById('iag_api_key');
    input.type = input.type === 'password' ? 'text' : 'password';
}

// ── Test API connection ───────────────────────────────────────────────────────
document.getElementById('iag-test-btn').addEventListener('click', function () {
    var btn    = this;
    var result = document.getElementById('iag-test-result');
    var apiKey = document.getElementById('iag_api_key').value;

    btn.disabled    = true;
    btn.textContent = 'Проверяю...';
    result.style.display = 'none';
    result.className     = 'iag-notice';

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'iag_test_connection', nonce: iagNonce, api_key: apiKey })
    })
    .then(r => r.json())
    .then(d => {
        result.style.display = 'block';
        result.classList.add(d.success ? 'iag-notice--ok' : 'iag-notice--err');
        result.textContent = d.success ? d.data : d.data;
        btn.disabled    = false;
        btn.textContent = 'Тест API';
    });
});

// ── Stats ─────────────────────────────────────────────────────────────────────
document.getElementById('iag-stats-btn').addEventListener('click', function () {
    var btn = this;
    btn.disabled    = true;
    btn.textContent = 'Загружаю...';

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'iag_get_stats', nonce: iagNonce })
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) return;
        var s   = d.data;
        var pct = s.percent;
        var r   = 28;
        var c   = 2 * Math.PI * r;
        var off = c - (pct / 100) * c;

        document.getElementById('iag-stats').innerHTML = `
            <div class="iag-donut">
                <svg viewBox="0 0 72 72" style="width:72px;height:72px;transform:rotate(-90deg)">
                    <circle cx="36" cy="36" r="${r}" fill="none" stroke="#f3f4f6" stroke-width="5"/>
                    <circle cx="36" cy="36" r="${r}" fill="none" stroke="#6366f1" stroke-width="5"
                        stroke-dasharray="${c.toFixed(1)}" stroke-dashoffset="${off.toFixed(1)}"
                        stroke-linecap="round" style="transition:stroke-dashoffset 0.6s"/>
                </svg>
                <div class="iag-donut__label">${pct}%</div>
            </div>
            <div class="iag-stats-grid">
                <div class="iag-stat">
                    <div class="iag-stat__n">${s.total.toLocaleString('ru')}</div>
                    <div class="iag-stat__l">Всего</div>
                </div>
                <div class="iag-stat iag-stat--ok">
                    <div class="iag-stat__n">${s.with_alt.toLocaleString('ru')}</div>
                    <div class="iag-stat__l">С alt</div>
                </div>
                <div class="iag-stat iag-stat--bad">
                    <div class="iag-stat__n">${s.no_alt.toLocaleString('ru')}</div>
                    <div class="iag-stat__l">Без alt</div>
                </div>
                <div class="iag-stat iag-stat--bad">
                    <div class="iag-stat__n">${s.bad_alt.toLocaleString('ru')}</div>
                    <div class="iag-stat__l">Мусор</div>
                </div>
            </div>`;
    });
});

// ── Batch generation ──────────────────────────────────────────────────────────
(function () {
    var batchBtn = document.getElementById('iag-batch-btn');
    var progress = document.getElementById('iag-batch-progress');
    var fill     = document.getElementById('iag-bar-fill');
    var label    = document.getElementById('iag-bar-label');
    var log      = document.getElementById('iag-batch-log');

    var running   = false;
    var processed = 0;
    var limit     = 0;

    batchBtn.addEventListener('click', function () {
        if (running) {
            running = false;
            batchBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg> Запустить';
            return;
        }
        limit     = parseInt(document.getElementById('iag-batch-limit').value) || 50;
        processed = 0;
        running   = true;
        log.innerHTML    = '';
        log.style.display    = 'block';
        progress.style.display = 'block';
        fill.style.width = '0%';
        label.textContent = 'Запускаю...';
        batchBtn.innerHTML = '⏹ Остановить';
        next();
    });

    function next() {
        if (!running || processed >= limit) {
            running = false;
            batchBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg> Запустить';
            label.textContent = 'Готово! Обработано: ' + processed;
            fill.style.width  = '100%';
            return;
        }

        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'iag_batch_next', nonce: iagNonce })
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                addLog('<span class="iag-log-err">✗ AJAX ошибка</span>');
                running = false;
                batchBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg> Запустить';
                return;
            }
            if (d.data.done) {
                running = false;
                batchBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg> Запустить';
                label.textContent = 'Все обработаны! Итого: ' + processed;
                fill.style.width  = '100%';
                return;
            }

            processed++;
            var pct = Math.round(processed / limit * 100);
            fill.style.width  = pct + '%';
            label.textContent = processed + ' / ' + limit;

            if (d.data.error) {
                addLog('<span class="iag-log-err">✗</span> #' + d.data.id + ' — ' + d.data.error);
            } else {
                addLog('<span class="iag-log-ok">✓</span> <a href="' + d.data.url + '" target="_blank">#' + d.data.id + '</a> — ' + d.data.alt);
            }
            next();
        })
        .catch(() => {
            addLog('<span class="iag-log-err">✗ Сетевая ошибка</span>');
            running = false;
            batchBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg> Запустить';
        });
    }

    function addLog(html) {
        var line = document.createElement('div');
        line.innerHTML = html;
        log.appendChild(line);
        log.scrollTop = log.scrollHeight;
    }
})();
