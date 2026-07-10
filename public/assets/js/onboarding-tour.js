/* TheraPano Onboarding Tour — selbstständig, keine externen Abhängigkeiten */
(function () {
    'use strict';

    /* ── Tour-Schritte je Praxis-Typ ─────────────────────────────────────── */
    var STEPS = {
        therapeut: [
            {
                target: null,
                title: 'Willkommen bei TheraPano! 🐾',
                text: 'Diese kurze Tour zeigt dir in wenigen Schritten die wichtigsten Bereiche deiner Praxis-Software. Du kannst die Tour jederzeit überspringen.',
                position: 'center'
            },
            {
                target: 'a[href="/dashboard"]',
                title: 'Dashboard',
                text: 'Dein persönliches Dashboard: Offene Rechnungen, bevorstehende Termine und die neuesten Patienten auf einen Blick.',
                position: 'right'
            },
            {
                target: 'a[href="/patienten"]',
                title: 'Patienten (Tiere)',
                text: 'Hier verwaltest du alle deine Patienten. Vollständige Patientenakte mit Befunden, Fotos, Dokumenten und Behandlungshistorie – alles an einem Ort.',
                position: 'right'
            },
            {
                target: 'a[href="/tierhalter"]',
                title: 'Tierhalter',
                text: 'Die Besitzer deiner Patienten. Du kannst sie per E-Mail ins Besitzerportal einladen, damit sie Hausaufgaben sehen und Rechnungen erhalten.',
                position: 'right'
            },
            {
                target: 'a[href="/rechnungen"]',
                title: 'Rechnungen',
                text: 'Erstelle und versende Rechnungen direkt aus TheraPano. Auf jeder Rechnung erscheint automatisch ein GiroCode-QR für schnelle Zahlungen.',
                position: 'right'
            },
            {
                target: 'a[href="/ausgaben"]',
                title: 'Ausgaben',
                text: 'Belege, Quittungen und Betriebsausgaben erfassen. Mit OCR-Erkennung und DATEV-Export für deinen Steuerberater.',
                position: 'right'
            },
            {
                target: 'a[href="/einstellungen"]',
                title: 'Einstellungen',
                text: 'Richte deine Praxis ein: Logo, Rechnungsdesign, Farben, E-Mail-Versand, Besitzerportal und vieles mehr.',
                position: 'right'
            },
            {
                target: '.sidebar-user',
                title: 'Dein Profil',
                text: 'Hier kannst du dein Passwort ändern, Benachrichtigungen verwalten und dich abmelden.',
                position: 'right'
            },
            {
                target: null,
                title: 'Alles bereit! 🎉',
                text: 'Du kennst jetzt die wichtigsten Bereiche von TheraPano. Leg einfach los – bei Fragen steht dir der Support jederzeit zur Verfügung.',
                position: 'center'
            }
        ],
        trainer: [
            {
                target: null,
                title: 'Willkommen bei TheraPano! 🐕',
                text: 'Diese kurze Tour zeigt dir in wenigen Schritten die wichtigsten Bereiche deiner Hundeschule-Software. Du kannst die Tour jederzeit überspringen.',
                position: 'center'
            },
            {
                target: 'a[href="/dashboard"]',
                title: 'Dashboard',
                text: 'Dein persönliches Dashboard: Aktuelle Buchungen, offene Rechnungen und die neuesten Hunde auf einen Blick.',
                position: 'right'
            },
            {
                target: 'a[href="/patienten"]',
                title: 'Hunde',
                text: 'Vollständige Akte für jeden Hund: Fotos, Trainingshistorie, Trainingspläne, Fortschritte und Dokumente.',
                position: 'right'
            },
            {
                target: 'a[href="/tierhalter"]',
                title: 'Hundebesitzer',
                text: 'Die Besitzer deiner Hunde. Lade sie ins Besitzerportal ein – so können sie Trainingspläne und Hausaufgaben einsehen.',
                position: 'right'
            },
            {
                target: 'a[href="/hundeschule"]',
                title: 'Hundeschule Dashboard',
                text: 'Dein zentrales Hundeschul-Dashboard: Überblick über aktive Kurse, Buchungen und Anwesenheiten.',
                position: 'right'
            },
            {
                target: 'a[href="/kurse"]',
                title: 'Kurse',
                text: 'Erstelle Kurse mit Terminen, Kapazitäten, Warteliste und Online-Buchung. Schüler können sich direkt anmelden.',
                position: 'right'
            },
            {
                target: 'a[href="/trainingsplaene"]',
                title: 'Trainingspläne',
                text: 'Erstelle individuelle Trainingspläne für jeden Hund – mit Übungen, Zielen und Fortschrittstracking.',
                position: 'right'
            },
            {
                target: 'a[href="/pakete"]',
                title: 'Pakete',
                text: 'Verkaufe Kurspakete und verwalte deren Einlösung. Kunden kaufen ein Paket und lösen Stunden nach Bedarf ein.',
                position: 'right'
            },
            {
                target: 'a[href="/interessenten"]',
                title: 'Interessenten',
                text: 'Leads und Interessenten verwalten: Von der ersten Anfrage bis zur Kurs-Buchung alles im Blick.',
                position: 'right'
            },
            {
                target: 'a[href="/einstellungen"]',
                title: 'Einstellungen',
                text: 'Richte deine Hundeschule ein: Logo, Rechnungsdesign, Farben, E-Mail-Versand und Besitzerportal.',
                position: 'right'
            },
            {
                target: null,
                title: 'Alles bereit! 🎉',
                text: 'Du kennst jetzt die wichtigsten Bereiche von TheraPano. Viel Erfolg mit deiner Hundeschule – der Support ist immer für dich da.',
                position: 'center'
            }
        ]
    };

    /* ── Hilfsfunktionen ─────────────────────────────────────────────────── */
    function getRect(el) {
        var r = el.getBoundingClientRect();
        return { top: r.top + window.scrollY, left: r.left + window.scrollX, width: r.width, height: r.height };
    }

    function clamp(val, min, max) { return Math.max(min, Math.min(max, val)); }

    /* ── Tour-Klasse ─────────────────────────────────────────────────────── */
    function Tour(type) {
        this.steps   = STEPS[type] || STEPS.therapeut;
        this.current = 0;
        this.overlay = null;
        this.box     = null;
        this.type    = type;
    }

    Tour.prototype.start = function () {
        this._injectStyles();
        this._buildOverlay();
        this._buildBox();
        document.body.appendChild(this.overlay);
        document.body.appendChild(this.box);
        this._show(0);
    };

    Tour.prototype._show = function (idx) {
        this.current = idx;
        var step = this.steps[idx];
        var total = this.steps.length;
        var isLast = idx === total - 1;
        var isFirst = idx === 0;

        /* ── highlight ── */
        document.querySelectorAll('.tour-highlight').forEach(function (el) {
            el.classList.remove('tour-highlight');
        });

        var targetEl = step.target ? document.querySelector(step.target) : null;

        if (targetEl) {
            targetEl.classList.add('tour-highlight');
            /* Sidebar ausklappen falls auf Mobile nötig */
            var sidebar = document.getElementById('sidebar');
            if (sidebar && !sidebar.classList.contains('open')) {
                sidebar.classList.add('open');
            }
        }

        /* ── Spotlight (SVG-Overlay) ── */
        this._updateSpotlight(targetEl);

        /* ── Box-Inhalt ── */
        var progressDots = '';
        for (var i = 0; i < total; i++) {
            progressDots += '<span class="tour-dot' + (i === idx ? ' active' : '') + '"></span>';
        }

        this.box.innerHTML =
            '<div class="tour-header">' +
                '<span class="tour-step-label">Schritt ' + (idx + 1) + ' von ' + total + '</span>' +
                '<button class="tour-skip" title="Tour überspringen">✕</button>' +
            '</div>' +
            '<div class="tour-progress">' + progressDots + '</div>' +
            '<h3 class="tour-title">' + step.title + '</h3>' +
            '<p class="tour-text">' + step.text + '</p>' +
            '<div class="tour-actions">' +
                (!isFirst ? '<button class="tour-btn tour-btn-secondary tour-prev">← Zurück</button>' : '<span></span>') +
                (isLast
                    ? '<button class="tour-btn tour-btn-primary tour-finish">Tour abschließen ✓</button>'
                    : '<button class="tour-btn tour-btn-primary tour-next">Weiter →</button>'
                ) +
            '</div>';

        /* Events */
        var self = this;
        this.box.querySelector('.tour-skip').onclick = function () { self.finish(); };
        var nextBtn = this.box.querySelector('.tour-next');
        var prevBtn = this.box.querySelector('.tour-prev');
        var finBtn  = this.box.querySelector('.tour-finish');
        if (nextBtn) nextBtn.onclick = function () { self._show(self.current + 1); };
        if (prevBtn) prevBtn.onclick = function () { self._show(self.current - 1); };
        if (finBtn)  finBtn.onclick  = function () { self.finish(); };

        /* ── Position der Box ── */
        this._positionBox(targetEl, step.position);
    };

    Tour.prototype._positionBox = function (targetEl, position) {
        var box = this.box;
        box.style.transform = '';
        box.style.top  = '';
        box.style.left = '';

        if (!targetEl || position === 'center') {
            box.style.position  = 'fixed';
            box.style.top       = '50%';
            box.style.left      = '50%';
            box.style.transform = 'translate(-50%, -50%)';
            return;
        }

        box.style.position = 'absolute';

        var r    = getRect(targetEl);
        var bw   = box.offsetWidth  || 320;
        var bh   = box.offsetHeight || 220;
        var vw   = window.innerWidth;
        var vh   = document.documentElement.scrollHeight;
        var pad  = 18;

        var top  = clamp(r.top + r.height / 2 - bh / 2, pad, vh - bh - pad);
        var left = r.left + r.width + pad;

        /* Falls rechts kein Platz → links positionieren */
        if (left + bw > vw - pad) {
            left = r.left - bw - pad;
        }
        left = clamp(left, pad, vw - bw - pad);

        box.style.top  = top + 'px';
        box.style.left = left + 'px';
    };

    Tour.prototype._updateSpotlight = function (targetEl) {
        var svg = this.overlay.querySelector('svg');
        var rect = svg.querySelector('rect.spot');
        var vw = window.innerWidth;
        var vh = window.innerHeight;

        svg.setAttribute('width', vw);
        svg.setAttribute('height', vh);

        if (!targetEl) {
            rect.setAttribute('x', '-9999');
            rect.setAttribute('y', '-9999');
            rect.setAttribute('width', '1');
            rect.setAttribute('height', '1');
            return;
        }

        var r   = targetEl.getBoundingClientRect();
        var pad = 8;
        var rx  = 8;
        rect.setAttribute('x',      r.left - pad);
        rect.setAttribute('y',      r.top  - pad);
        rect.setAttribute('width',  r.width  + pad * 2);
        rect.setAttribute('height', r.height + pad * 2);
        rect.setAttribute('rx',     rx);
    };

    Tour.prototype._buildOverlay = function () {
        var vw = window.innerWidth;
        var vh = window.innerHeight;

        this.overlay = document.createElement('div');
        this.overlay.className = 'tour-overlay';
        this.overlay.innerHTML =
            '<svg width="' + vw + '" height="' + vh + '" xmlns="http://www.w3.org/2000/svg">' +
                '<defs>' +
                    '<mask id="tour-mask">' +
                        '<rect width="100%" height="100%" fill="white"/>' +
                        '<rect class="spot" x="-9999" y="-9999" width="1" height="1" rx="8" fill="black"/>' +
                    '</mask>' +
                '</defs>' +
                '<rect width="100%" height="100%" fill="rgba(0,0,0,0.72)" mask="url(#tour-mask)"/>' +
            '</svg>';

        /* Overlay auf Fenstergröße-Änderungen reagieren */
        var self = this;
        window._tourResizeHandler = function () {
            var step = self.steps[self.current];
            var el   = step.target ? document.querySelector(step.target) : null;
            var svg  = self.overlay.querySelector('svg');
            svg.setAttribute('width',  window.innerWidth);
            svg.setAttribute('height', window.innerHeight);
            self._updateSpotlight(el);
            self._positionBox(el, step.position);
        };
        window.addEventListener('resize', window._tourResizeHandler);
    };

    Tour.prototype._buildBox = function () {
        this.box = document.createElement('div');
        this.box.className = 'tour-box';
    };

    Tour.prototype.finish = function () {
        /* Aufräumen */
        document.querySelectorAll('.tour-highlight').forEach(function (el) {
            el.classList.remove('tour-highlight');
        });
        if (this.overlay && this.overlay.parentNode) this.overlay.parentNode.removeChild(this.overlay);
        if (this.box    && this.box.parentNode)     this.box.parentNode.removeChild(this.box);
        if (window._tourResizeHandler) {
            window.removeEventListener('resize', window._tourResizeHandler);
            delete window._tourResizeHandler;
        }

        /* Server informieren — Tour abgeschlossen */
        var csrf = document.querySelector('meta[name="csrf-token"]');
        fetch('/api/onboarding/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf ? csrf.getAttribute('content') : ''
            },
            credentials: 'same-origin'
        }).catch(function () { /* ignorieren */ });
    };

    Tour.prototype._injectStyles = function () {
        if (document.getElementById('tour-styles')) return;
        var s = document.createElement('style');
        s.id = 'tour-styles';
        s.textContent = [
            '.tour-overlay{position:fixed;inset:0;z-index:9998;pointer-events:none;}',
            '.tour-overlay svg{display:block;pointer-events:all;}',
            '.tour-highlight{position:relative;z-index:9999!important;box-shadow:0 0 0 4px #6366f1,0 0 0 6px rgba(99,102,241,.3)!important;border-radius:8px;transition:box-shadow .3s;}',
            '.tour-box{position:absolute;z-index:10000;background:var(--card-bg,#1e1e2e);border:1px solid rgba(99,102,241,.35);border-radius:16px;padding:24px;width:320px;max-width:calc(100vw - 32px);box-shadow:0 24px 64px rgba(0,0,0,.55);animation:tour-pop .25s cubic-bezier(.34,1.56,.64,1);}',
            '@keyframes tour-pop{from{opacity:0;transform:scale(.88) translateY(8px)}to{opacity:1;transform:scale(1) translateY(0)}}',
            '.tour-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}',
            '.tour-step-label{font-size:.72rem;font-weight:600;color:#6366f1;text-transform:uppercase;letter-spacing:.06em;}',
            '.tour-skip{background:none;border:none;color:var(--text-muted,#94a3b8);cursor:pointer;font-size:1rem;padding:2px 4px;border-radius:4px;line-height:1;}',
            '.tour-skip:hover{color:var(--text,#e2e8f0);}',
            '.tour-progress{display:flex;gap:5px;margin-bottom:14px;}',
            '.tour-dot{width:6px;height:6px;border-radius:50%;background:rgba(99,102,241,.25);transition:background .2s;}',
            '.tour-dot.active{background:#6366f1;width:18px;border-radius:3px;}',
            '.tour-title{margin:0 0 8px;font-size:1.05rem;font-weight:700;color:var(--text,#e2e8f0);}',
            '.tour-text{margin:0 0 20px;font-size:.88rem;line-height:1.55;color:var(--text-muted,#94a3b8);}',
            '.tour-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;}',
            '.tour-btn{padding:9px 18px;border-radius:9px;font-size:.85rem;font-weight:600;cursor:pointer;border:none;transition:opacity .15s,transform .1s;}',
            '.tour-btn:active{transform:scale(.97);}',
            '.tour-btn-primary{background:#6366f1;color:#fff;}',
            '.tour-btn-primary:hover{background:#5153d3;}',
            '.tour-btn-secondary{background:rgba(99,102,241,.12);color:#818cf8;}',
            '.tour-btn-secondary:hover{background:rgba(99,102,241,.2);}'
        ].join('');
        document.head.appendChild(s);
    };

    /* ── Start sobald DOM bereit ist ────────────────────────────────────── */
    function init(type) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { new Tour(type).start(); });
        } else {
            /* Kurz verzögern damit Layout gerendert ist */
            setTimeout(function () { new Tour(type).start(); }, 400);
        }
    }

    window.TheraPanoTour = { start: init };
})();
