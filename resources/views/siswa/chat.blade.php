@extends('layouts.app')
@section('title', 'Chat · ' . ($guruName ?? 'Guru BK'))
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/chatRoom.css') }}">
<style>
body { margin:0; background:#f5f5f0; }
.chat-shell {
  --bg:#f5f5f0; --surface:#fff; --ink-900:#1a1a18; --ink-600:#5F5E5A; --border:#e8e6e0;
  --accent:#534AB7; --accent-soft:#EEEDFE;
  font-family: Inter, system-ui, sans-serif;
  display:flex; flex-direction:column; height:100vh; max-width:860px; margin:0 auto;
  background:var(--surface); border-left:1px solid var(--border); border-right:1px solid var(--border);
}
.chat-header { padding:14px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; gap:12px; flex-shrink:0; }
.chat-header-info { display:flex; align-items:center; gap:12px; min-width:0; }
.chat-header-avatar { width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg,#534AB7,#7F77DD); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0; }
.chat-header-text h2 { margin:0; font-size:16px; font-weight:700; }
.chat-header-text p { margin:2px 0 0; font-size:12.5px; color:var(--ink-600); }
.conn-pill { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--ink-600); background:#f5f5f0; padding:4px 10px; border-radius:999px; }
.conn-dot { width:8px; height:8px; border-radius:50%; background:#1D9E75; }
.btn-back { text-decoration:none; color:var(--accent); font-size:13px; font-weight:600; }
.info-banner { padding:10px 20px; font-size:12.5px; background:#EAF6EF; color:#1E8E5A; flex-shrink:0; }
.info-banner.warn { background:#FBEEEA; color:#B4432F; }
.chat-messages { flex:1; overflow-y:auto; padding:18px 20px; display:flex; flex-direction:column; gap:10px; background:#faf9f7; }
.chat-msg { max-width:78%; display:flex; flex-direction:column; }
.chat-msg.sent { align-self:flex-end; align-items:flex-end; }
.chat-msg.received { align-self:flex-start; align-items:flex-start; }
.msg-meta { font-size:11px; color:#888; margin-bottom:4px; }
.msg-bubble { padding:10px 14px; border-radius:14px; font-size:14px; line-height:1.45; word-break:break-word; white-space:pre-wrap; }
.chat-msg.sent .msg-bubble { background:var(--accent); color:#fff; border-bottom-right-radius:4px; }
.chat-msg.received .msg-bubble { background:#fff; border:1px solid var(--border); border-bottom-left-radius:4px; }
.chat-input-area { display:flex; gap:10px; padding:14px 16px; border-top:1px solid var(--border); background:#fff; flex-shrink:0; }
.chat-input { flex:1; padding:12px 14px; border:1px solid var(--border); border-radius:12px; font-size:14px; font-family:inherit; outline:none; }
.chat-input:focus { border-color:var(--accent); }
.send-btn { width:44px; height:44px; border:none; border-radius:12px; background:var(--accent); color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.send-btn:disabled { opacity:.5; cursor:not-allowed; }
</style>
@endpush

@section('content')
@php
  $peerName = $guruName;
  $peerInitial = strtoupper(mb_substr($peerName, 0, 1));
  $sendUrl = route('siswa.chat.send', $row->id);
  $pollUrl = route('siswa.chat.messages', $row->id);
  $backUrl = route('siswa.status', $row->id);
@endphp
<div class="chat-shell">
  <div class="chat-header">
    <div class="chat-header-info">
      <div class="chat-header-avatar">{{ $peerInitial }}</div>
      <div class="chat-header-text">
        <h2>{{ $peerName }}</h2>
        <p>Konseling daring · #{{ $row->id }} · {{ $row->kategori ?? '' }}</p>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <span class="conn-pill"><span class="conn-dot"></span><span id="connText">Terhubung</span></span>
      <a href="{{ $backUrl }}" class="btn-back">← Kembali</a>
    </div>
  </div>
  <div class="info-banner" id="infoBanner">✅ Chat aktif · Pesan tersimpan di sesi konseling ini</div>
  <div class="chat-messages" id="chatMessages">
    @forelse($messages as $m)
      @php $isSent = ($m->sender_type === 'siswa') || ((string)$m->sender_id === $myId); @endphp
      <div class="chat-msg {{ $isSent ? 'sent' : 'received' }}" data-id="{{ $m->id }}">
        <div class="msg-meta">{{ $isSent ? 'Saya' : ($m->sender_name ?: $peerName) }} · {{ $m->created_at ? \Carbon\Carbon::parse($m->created_at)->format('H:i') : '' }}</div>
        <div class="msg-bubble">{{ $m->message }}</div>
      </div>
    @empty
      <div id="emptyHint" style="text-align:center;color:#888;padding:24px;font-size:13px">Belum ada pesan. Mulai percakapan.</div>
    @endforelse
  </div>
  <form class="chat-input-area" id="chatForm">
    @csrf
    <input type="text" id="chatInput" class="chat-input" placeholder="Tulis pesan..." autocomplete="off" maxlength="2000">
    <button type="submit" class="send-btn" id="sendBtn" aria-label="Kirim">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </form>
</div>
@endsection

@push('scripts')
<script>
(function(){
  var myId = @json($myId);
  var peerName = @json($peerName);
  var myType = 'siswa';
  var box = document.getElementById('chatMessages');
  var form = document.getElementById('chatForm');
  var input = document.getElementById('chatInput');
  var sendUrl = @json($sendUrl);
  var pollUrl = @json($pollUrl);
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var lastId = 0;
  box.querySelectorAll('.chat-msg[data-id]').forEach(function(el){
    var id = parseInt(el.getAttribute('data-id'),10);
    if(id>lastId) lastId = id;
  });
  function scrollBottom(){ box.scrollTop = box.scrollHeight; }
  scrollBottom();

  function appendMsg(m){
    if(document.querySelector('.chat-msg[data-id="'+m.id+'"]')) return;
    var empty = document.getElementById('emptyHint');
    if(empty) empty.remove();
    var isSent = (m.sender_type === myType) || (String(m.sender_id) === String(myId));
    var div = document.createElement('div');
    div.className = 'chat-msg ' + (isSent ? 'sent' : 'received');
    div.setAttribute('data-id', m.id);
    var who = isSent ? 'Saya' : (m.sender_name || peerName);
    // Nama pengirim (sender_name) berasal dari data pengguna, jadi TIDAK
    // boleh digabung sebagai string HTML lewat innerHTML — buat elemen DOM
    // lalu isi dengan textContent agar tidak bisa dieksploitasi sebagai
    // stored XSS.
    var metaDiv = document.createElement('div');
    metaDiv.className = 'msg-meta';
    metaDiv.textContent = who + ' · ' + (m.time || '');
    var bubbleDiv = document.createElement('div');
    bubbleDiv.className = 'msg-bubble';
    bubbleDiv.textContent = m.message;
    div.appendChild(metaDiv);
    div.appendChild(bubbleDiv);
    box.appendChild(div);
    if(m.id > lastId) lastId = m.id;
    scrollBottom();
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var text = input.value.trim();
    if(!text) return;
    input.value = '';
    fetch(sendUrl, {
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'Accept':'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With':'XMLHttpRequest'
      },
      body: JSON.stringify({ message: text })
    }).then(function(r){ return r.json(); })
      .then(function(j){ if(j.success && j.data) appendMsg(j.data); })
      .catch(function(){ alert('Gagal mengirim pesan'); });
  });

  setInterval(function(){
    fetch(pollUrl + '?after_id=' + lastId, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if(!j.success || !j.data) return;
        j.data.forEach(appendMsg);
        document.getElementById('connText').textContent = 'Terhubung';
        document.getElementById('infoBanner').className = 'info-banner';
        document.getElementById('infoBanner').textContent = '✅ Chat aktif · Pesan tersimpan di sesi konseling ini';
      })
      .catch(function(){
        document.getElementById('connText').textContent = 'Terputus';
        document.getElementById('infoBanner').className = 'info-banner warn';
        document.getElementById('infoBanner').textContent = '⚠️ Koneksi terputus · mencoba ulang...';
      });
  }, 2500);
})();
</script>
@endpush
