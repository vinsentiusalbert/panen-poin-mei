<div class="festive-background" aria-hidden="true">
    @for ($i = 0; $i < 28; $i++)
        <span class="festive-snow" style="--x: {{ ($i * 37 + 3) % 100 }}%; --size: {{ 2 + $i % 4 }}px; --duration: {{ 12 + $i % 9 }}s; --delay: -{{ $i * 1.7 }}s; --drift: {{ ($i % 2 ? 1 : -1) * (18 + $i % 35) }}px"></span>
    @endfor
    @for ($i = 0; $i < 10; $i++)
        <span class="festive-sparkle" style="--x: {{ ($i * 29 + 7) % 100 }}%; --y: {{ ($i * 17 + 9) % 100 }}%; --delay: -{{ $i * 0.8 }}s"></span>
    @endfor
    <span class="festive-firework festive-firework--one"></span>
    <span class="festive-firework festive-firework--two"></span>
    <span class="festive-firework festive-firework--three"></span>
</div>
<button class="festive-motion-toggle" type="button" aria-pressed="false" hidden>Jeda animasi</button>
<script src="{{ asset('js/festive-background.js') }}?v=20261124-1" defer></script>
