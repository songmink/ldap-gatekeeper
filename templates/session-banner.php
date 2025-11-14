<?php
if ( ! isset($lg_session) || ! is_array($lg_session) ) {
    return;
}

$info       = $lg_session['info'] ?? [];
$mm         = $lg_session['mm'] ?? 0;
$ss         = $lg_session['ss'] ?? 0;
$logout_url = $lg_session['logout_url'] ?? '#';
?>
<style>
    .lg-session-banner {
        position: fixed;
        top: 14px;
        right: 14px;
        background: rgba(0,0,0,0.75);
        color: #fff;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.3;
        z-index: 999999;
        display: flex;
        gap: 8px;
        align-items: center;
        backdrop-filter: blur(4px);
    }
    .lg-session-banner strong { color: #fff; }
    .lg-session-banner .lg-email { color: #cbd5e1; }
    .lg-session-banner a.lg-logout {
        color: #93c5fd;
        text-decoration: none;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #60a5fa;
        font-size: 12px;
    }
    .lg-session-banner a.lg-logout:hover {
        background: #60a5fa;
        color: #000;
    }
</style>

<div class="lg-session-banner">
    <strong><?php echo esc_html( $info['login'] ?? '' ); ?></strong>

    <?php if ( ! empty($info['email']) ): ?>
        <span class="lg-email">(<?php echo esc_html($info['email']); ?>)</span>
    <?php endif; ?>

    <span id="lg-remaining">
        · <?php echo sprintf('%02d:%02d', (int)$mm, (int)$ss); ?> left
    </span>

    <a class="lg-logout" href="<?php echo esc_url($logout_url); ?>">Logout</a>
</div>

<script>
(function(){
    var el = document.getElementById('lg-remaining');
    if (!el) return;
    var secs = <?php echo (int) ($lg_session['remaining'] ?? 0); ?>;

    function tick() {
        if (secs <= 0) return;
        secs--;
        var mm = String(Math.floor(secs/60)).padStart(2,'0');
        var ss = String(secs%60).padStart(2,'0');
        el.textContent = '· ' + mm + ':' + ss + ' left';
        setTimeout(tick, 1000);
    }

    setTimeout(tick, 1000);
})();
</script>
