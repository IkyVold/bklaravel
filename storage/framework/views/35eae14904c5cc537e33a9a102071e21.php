<?php $__env->startSection('title', 'Beranda'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/beranda.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $chatSessions = $chatSessions ?? collect();
?>
<div class="beranda-page">
    <div class="hero">
        <div class="hero-left">
            <h1>
                Halo, <span><?php echo e($siswa->nama ?? $siswa->nis ?? 'Siswa'); ?></span>.<br>
                Anda tidak sendirian.
            </h1>
            <p>
                Sistem konseling aman dan terpercaya untuk melaporkan, berdiskusi, dan mendapatkan bantuan dari guru
                BK profesional.
            </p>
            <div class="hero-actions">
                <a href="<?php echo e(route('siswa.konseling.create')); ?>" class="cta-button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    Ajukan konseling
                </a>
                <button type="button" class="faq-button" id="faqOpenBtn" aria-label="Buka FAQ chatbot">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                    Ada pertanyaan cepat? Tanya FAQ
                </button>
            </div>
        </div>
        <div class="hero-right">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Guru BK siap membantu</div>
                    <div class="stat-value">3 konselor tersedia</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Privasi terjaga</div>
                    <div class="stat-value">Data Anda aman &amp; rahasia</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon coral">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                    </svg>
                </div>
                <div>
                    <div class="stat-label">Mode konseling</div>
                    <div class="stat-value">Tatap muka &amp; daring</div>
                </div>
            </div>
        </div>
    </div>

    
    <button type="button" class="chat-float-btn" id="chatFloatBtn" title="Buka pesan" aria-label="Buka pesan">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
        <?php if($chatSessions->count() > 0): ?>
            <span class="chat-badge-float"><?php echo e($chatSessions->count() > 9 ? '9+' : $chatSessions->count()); ?></span>
        <?php endif; ?>
    </button>

    <div class="chat-widget" id="chatWidget">
        <div class="widget-header">
            <div class="widget-header-info">
                <div class="widget-header-avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                    </svg>
                </div>
                <div class="widget-header-text">
                    <h3>Pesan</h3>
                    <p>Konseling dengan Guru BK</p>
                </div>
            </div>
            <button type="button" class="widget-close" id="chatWidgetClose" title="Tutup" aria-label="Tutup">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="widget-tabs">
            <button type="button" class="tab-btn active" data-tab="chats">Chat</button>
            <button type="button" class="tab-btn" data-tab="contacts">Kontak</button>
        </div>
        <div class="chat-list" id="tabChats">
            <?php if($chatSessions->isEmpty()): ?>
                <div class="empty-chat">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                        </svg>
                    </div>
                    <div class="empty-title">Belum ada percakapan</div>
                    <div class="empty-desc">Lakukan konseling daring dan tunggu konfirmasi Guru BK untuk mulai chat</div>
                </div>
            <?php else: ?>
                <?php $__currentLoopData = $chatSessions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cs): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('siswa.chat', $cs->id)); ?>" class="chat-item" style="text-decoration:none;color:inherit">
                        <div class="chat-avatar"><?php echo e(strtoupper(mb_substr($cs->guru_bk ?? 'G', 0, 1))); ?></div>
                        <div class="chat-item-body">
                            <div class="chat-item-top">
                                <span class="chat-item-name"><?php echo e($cs->guru_bk ?: 'Guru BK'); ?></span>
                                <span class="chat-item-time"><?php echo e($cs->tanggal ? \Carbon\Carbon::parse($cs->tanggal)->format('d M') : ''); ?></span>
                            </div>
                            <div class="chat-item-preview"><?php echo e(\Illuminate\Support\Str::limit($cs->deskripsi ?? 'Konseling telah dikonfirmasi', 50)); ?></div>
                            <div class="chat-item-meta"><?php echo e($cs->kategori ?: 'Konseling'); ?> · Daring</div>
                        </div>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        </div>
        <div class="contacts-list" id="tabContacts" style="display:none">
            <div class="empty-chat">
                <div class="empty-title">Ajukan konseling dulu</div>
                <div class="empty-desc">Untuk chat, pilih Guru BK lewat menu Konseling dan pilih mode Daring.</div>
                <a href="<?php echo e(route('siswa.konseling.create')); ?>" class="cta-button" style="margin-top:12px;display:inline-flex">Pilih Guru BK</a>
            </div>
        </div>
    </div>

    
    <button type="button" class="ai-chat-fab" id="aiFab" title="Asisten FAQ BK" aria-label="Buka chatbot FAQ">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
    </button>

    <div class="ai-chat-modal" id="faqPanel" role="dialog" aria-labelledby="faqTitle">
        <div class="ai-chat-header">
            <div class="ai-chat-header-info">
                <div class="ai-chat-avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                </div>
                <div>
                    <div class="ai-chat-title" id="faqTitle">Asisten FAQ BK</div>
                    <div class="ai-chat-subtitle">Tanya prosedur konseling, jadwal, privasi</div>
                </div>
            </div>
            <button type="button" class="ai-chat-close" id="faqCloseBtn" aria-label="Tutup">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="ai-chat-messages" id="faqMessages">
            <div class="ai-chat-msg bot">
                <div class="ai-msg-avatar">?</div>
                <div class="ai-msg-bubble">Halo! 👋 Saya asisten konseling AI. Ada yang ingin kamu tanyakan atau ceritakan?</div>
            </div>
        </div>
        <div class="ai-chat-input-area">
            <input type="text" id="faqInput" class="ai-chat-input-field" placeholder="Ketik pertanyaan..." autocomplete="off" maxlength="500">
            <button type="button" id="faqSend" class="ai-chat-send" aria-label="Kirim">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                    <line x1="22" y1="2" x2="11" y2="13" /><polygon points="22 2 15 22 11 13 2 9 22 2" />
                </svg>
            </button>
        </div>
        <div class="ai-chat-footer-note">Jawaban otomatis — untuk masalah serius hubungi Guru BK</div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    /* ---- Chat widget (kanan) ---- */
    var floatBtn = document.getElementById('chatFloatBtn');
    var widget = document.getElementById('chatWidget');
    var widgetClose = document.getElementById('chatWidgetClose');
    function toggleWidget(force) {
        if (!widget) return;
        var open = force !== undefined ? force : !widget.classList.contains('show');
        widget.classList.toggle('show', open);
    }
    if (floatBtn) floatBtn.addEventListener('click', function (e) { e.stopPropagation(); toggleWidget(); });
    if (widgetClose) widgetClose.addEventListener('click', function () { toggleWidget(false); });
    document.addEventListener('click', function (e) {
        if (!widget || !widget.classList.contains('show')) return;
        if (widget.contains(e.target) || (floatBtn && floatBtn.contains(e.target))) return;
        toggleWidget(false);
    });
    document.querySelectorAll('.widget-tabs .tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.widget-tabs .tab-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            var tab = btn.getAttribute('data-tab');
            document.getElementById('tabChats').style.display = tab === 'chats' ? '' : 'none';
            document.getElementById('tabContacts').style.display = tab === 'contacts' ? '' : 'none';
        });
    });

    /* ---- AI FAQ (kiri) ---- */
    var panel = document.getElementById('faqPanel');
    var openBtn = document.getElementById('faqOpenBtn');
    var fab = document.getElementById('aiFab');
    var closeBtn = document.getElementById('faqCloseBtn');
    var messages = document.getElementById('faqMessages');
    var input = document.getElementById('faqInput');
    var sendBtn = document.getElementById('faqSend');
    var aiUrl = <?php echo json_encode(route('siswa.ai.faq'), 15, 512) ?>;
    var csrf = document.querySelector('meta[name="csrf-token"]');
    csrf = csrf ? csrf.getAttribute('content') : '';

    function openFaq() {
        if (!panel) return;
        panel.classList.add('open');
        if (fab) fab.classList.add('active');
        if (input) setTimeout(function () { input.focus(); }, 50);
    }
    function closeFaq() {
        if (!panel) return;
        panel.classList.remove('open');
        if (fab) fab.classList.remove('active');
    }
    if (openBtn) openBtn.addEventListener('click', openFaq);
    if (fab) fab.addEventListener('click', function () {
        if (panel && panel.classList.contains('open')) closeFaq(); else openFaq();
    });
    if (closeBtn) closeBtn.addEventListener('click', closeFaq);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeFaq(); });

    function appendMsg(role, text) {
        if (!messages) return;
        var wrap = document.createElement('div');
        wrap.className = 'ai-chat-msg ' + (role === 'user' ? 'user' : 'bot');
        if (role === 'user') {
            wrap.innerHTML = '<div class="ai-msg-bubble"></div>';
        } else {
            wrap.innerHTML = '<div class="ai-msg-avatar">?</div><div class="ai-msg-bubble"></div>';
        }
        wrap.querySelector('.ai-msg-bubble').textContent = text;
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;
    }

    var chatHistory = []; // {role, content} — selaras dengan backend Node /api/chat
    var isSending = false;
    var cooldownUntil = 0; // timestamp ms — cegah spam setelah rate-limit

    function setSending(on) {
        isSending = !!on;
        if (input) input.disabled = !!on;
        if (sendBtn) sendBtn.disabled = !!on;
    }

    function sendFaq() {
        if (!input || isSending) return;
        var now = Date.now();
        if (now < cooldownUntil) {
            var sisa = Math.ceil((cooldownUntil - now) / 1000);
            appendMsg('bot', 'Tunggu ' + sisa + ' detik lagi sebelum mengirim pesan berikutnya, ya.');
            return;
        }
        var text = (input.value || '').trim();
        if (!text) return;
        appendMsg('user', text);
        chatHistory.push({ role: 'user', content: text });
        input.value = '';
        setSending(true);
        fetch(aiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ messages: chatHistory })
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              var j = res.j || {};
              var reply = j.reply || (j.error && j.error.message) || j.message || 'Maaf, tidak ada jawaban.';
              appendMsg('bot', reply);
              if (j.reply && !j.rate_limited) {
                  chatHistory.push({ role: 'assistant', content: j.reply });
              }
              // Jika kena rate-limit, cooldown 12 detik di sisi browser
              if (j.rate_limited || (typeof reply === 'string' && reply.indexOf('terlalu banyak') !== -1) || (typeof reply === 'string' && reply.indexOf('server AI sedang sibuk') !== -1)) {
                  cooldownUntil = Date.now() + 12000;
              } else {
                  // jeda singkat antar pesan agar tidak menabrak batas RPM Groq free tier
                  cooldownUntil = Date.now() + 1500;
              }
          })
          .catch(function () {
              appendMsg('bot', 'Gagal menghubungi asisten. Coba lagi atau hubungi Guru BK.');
          })
          .finally(function () {
              setSending(false);
              if (input) input.focus();
          });
    }
    if (sendBtn) sendBtn.addEventListener('click', function (e) { e.preventDefault(); sendFaq(); });
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); sendFaq(); }
        });
    }
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/siswa/dashboard.blade.php ENDPATH**/ ?>