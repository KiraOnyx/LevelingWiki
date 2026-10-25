<?php // Original vector artwork, phase A. Separate definitions preserve the first 2D renderer verbatim. ?>
<svg class="tower-defs" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
    <linearGradient id="relief-front"><stop stop-color="#364354"/><stop offset=".32" stop-color="#536172"/><stop offset=".7" stop-color="#313e53"/><stop offset="1" stop-color="#202d41"/></linearGradient>
    <linearGradient id="relief-side"><stop stop-color="#233147"/><stop offset="1" stop-color="#0d182b"/></linearGradient>
    <linearGradient id="relief-bevel" x2=".4" y2="1"><stop stop-color="#9aaac0"/><stop offset=".22" stop-color="#52647d"/><stop offset=".55" stop-color="#304057"/><stop offset="1" stop-color="#142039"/></linearGradient>
    <linearGradient id="relief-top" x2="1" y2="1"><stop stop-color="#a4b8cf"/><stop offset=".5" stop-color="#5d718e"/><stop offset="1" stop-color="#35465f"/></linearGradient>
    <linearGradient id="relief-shadow" x2="0" y2="1"><stop stop-color="#030c1b" stop-opacity=".9"/><stop offset="1" stop-color="#030c1b" stop-opacity="0"/></linearGradient>
    <linearGradient id="relief-light" x2="1" y2="1"><stop stop-color="#b6daff" stop-opacity=".17"/><stop offset=".55" stop-color="#8a9ed5" stop-opacity="0"/><stop offset="1" stop-color="#7155cf" stop-opacity=".16"/></linearGradient>
    <linearGradient id="relief-glass" x2="0" y2="1"><stop stop-color="#c0deff"/><stop offset=".3" stop-color="#869bdc"/><stop offset=".66" stop-color="#524b9e"/><stop offset="1" stop-color="#18233c"/></linearGradient>
    <radialGradient id="relief-glow"><stop stop-color="#b5b6ff" stop-opacity=".58"/><stop offset=".3" stop-color="#8d7cfd" stop-opacity=".23"/><stop offset="1" stop-color="#7261ff" stop-opacity="0"/></radialGradient>
    <pattern id="relief-stone" width="108" height="76" patternUnits="userSpaceOnUse">
        <path d="M1 2 50 1 53 33 2 35ZM56 2 106 1 105 34 57 33ZM-26 39 23 38 25 72-25 74ZM28 39 77 38 80 72 28 74ZM83 39 133 38 134 73 83 72Z" fill="#151f32"/>
        <path d="m4 5 43-1 3 25-45 2ZM59 5l43-1-1 26-41-1ZM-23 42l43-1 2 28-45 1ZM31 42l43-1 3 28-46 1ZM86 42l44-1 1 28-45 1Z" fill="#6e7c90" fill-opacity=".17"/>
        <path d="m2 3 48-1-3 4H6L5 30l-3 4ZM57 3l48-1-4 4-40 1-1 22-3 3ZM29 40l48-1-4 4-40 2-1 24-3 3ZM84 40l48-1-4 4-40 2-1 24-3 3Z" fill="#bdcce0" fill-opacity=".25"/>
        <path d="m7 31 43-2 3 4-50 2ZM61 30l40 0 4 4-48-1ZM34 70l43-1 3 3-52 2ZM88 70l43-1 3 4-51-1Z" fill="#020b1c" fill-opacity=".6"/>
        <path d="m13 13 7-1 5 4m46-5 5 1m-29 38 8 2 2 5m36-6 7-2" fill="none" stroke="#101a2d" stroke-opacity=".5"/>
        <path d="m10 21 13-1m43 4 13-1m-44 37 15-2" stroke="#bdcde4" stroke-opacity=".08"/>
    </pattern>
    <symbol id="relief-window" viewBox="0 0 66 124">
        <!-- Thick archivolt, bevel, dark reveal, recessed glass and projecting sill. -->
        <path d="M1 115V48Q1 20 32 1q32 20 32 47v67Z" fill="#080f1d"/>
        <path d="M3 112V47Q5 21 32 5q27 17 28 42v65Z" fill="url(#relief-bevel)" stroke="#6f8098"/>
        <path d="M11 107V49q0-22 21-35 21 13 21 35v58Z" fill="#071121" stroke="#101b2c" stroke-width="2"/>
        <path d="M20 104V51q0-17 16-28 13 12 13 28v53Z" fill="url(#relief-glass)"/>
        <path d="M12 106V49q1-21 20-34l4 8q-16 13-16 28v53Z" fill="#0e1729"/>
        <path d="M35 25v79M20 65h29M20 84h29m-28-23q3-15 14-20 10 6 13 20" fill="none" stroke="#1b2942" stroke-width="2.5"/>
        <path d="m8 39 7 3M16 23l6 5M30 7l1 9m15 5-6 6m16 9-7 5M5 59h7M5 80h7m42-21h6m-6 21h6" stroke="#0c182a" stroke-width="2"/>
        <path d="m0 111 10-7h48l8 7-5 9H3Z" fill="url(#relief-top)"/><path d="M3 113h58v7H3Z" fill="#33455f"/>
        <path d="M4 120h56l-6 4H10Z" fill="#081324"/>
    </symbol>
    <symbol id="relief-crystal" viewBox="0 0 64 100">
        <ellipse cx="32" cy="49" rx="32" ry="49" fill="url(#relief-glow)"/>
        <path d="m32 14 12 28-12 37-12-37Z" fill="#b4b7ff"/><path d="m32 14 0 65-12-37Z" fill="#7764cc"/>
        <path d="m32 14 12 28-12-5Z" fill="#e2edff"/><path d="m32 37 12 5-12 37Z" fill="#a69aef"/>
        <path d="M22 81h20l-5 6H27Z" fill="#71849d"/><path d="M26 87h12v6H26Z" fill="#1e3049"/>
    </symbol>
    <symbol id="relief-column" viewBox="0 0 50 320">
        <path d="M7 0h27l11 13v307H7Z" fill="#071221" opacity=".75"/>
        <path d="M0 0h27v320H0Z" fill="url(#relief-bevel)"/><path d="M27 0 40 10v310H27Z" fill="#16253c"/>
        <path d="M3 0h5v320H3Z" fill="#a6b9d3" opacity=".25"/><path d="M22 0h3v320h-3Z" fill="#09172b" opacity=".7"/>
        <path d="M0 81h27l13 7M0 164h27l13 7M0 247h27l13 7" fill="none" stroke="#071426" stroke-width="3"/>
        <path d="M1 84h24M1 167h24M1 250h24" stroke="#8294ac" stroke-opacity=".5"/>
        <path d="m-3 269 16-15 24 7 10 20-12 12H-3Z" fill="url(#relief-top)"/>
        <path d="M-3 274h30v19H-3Z" fill="url(#relief-bevel)"/><path d="m27 274 10-13 10 20-12 12h-8Z" fill="#17273e"/>
    </symbol>
    <symbol id="relief-cornice" viewBox="0 0 320 90">
        <path d="m24 29 277 1-23 51H53Z" fill="url(#relief-shadow)"/>
        <path d="m14 18 26-13h226l40 14-22 12H35Z" fill="url(#relief-top)"/>
        <path d="M14 18h270v15H14Z" fill="url(#relief-bevel)"/><path d="m284 18 22 1v14l-22 9Z" fill="#15243b"/>
        <path d="M18 20h262" stroke="#b6c6d9" stroke-opacity=".55"/>
        <path d="m22 34 15 10h241l15-10Z" fill="#071526"/><path d="M29 43h247v10H29Z" fill="url(#relief-bevel)"/>
        <path d="M37 55v17m18-17v17m19-17v17m168-17v17m19-17v17" stroke="#121f33" stroke-width="9"/>
        <path d="M34 55v12m18-12v12m19-12v12m168-12v12m19-12v12" stroke="#6d7d96" stroke-width="4"/>
    </symbol>
    <symbol id="tower-floor-relief" viewBox="0 0 320 320">
        <path d="M33 0h221v320H33Z" fill="url(#relief-front)"/><path d="M254 0h33v320h-33Z" fill="url(#relief-side)"/>
        <path d="M33 0h254v320H33Z" fill="url(#relief-stone)"/>
        <path d="M254 0h33v320h-33Z" fill="#061225" opacity=".48"/>
        <path d="M72 74h15l30 246H72ZM242 78l20 12 7 230h-27Z" fill="#081425" opacity=".44"/>
        <path d="M60 0h189v320H60Z" fill="url(#relief-light)"/>
        <ellipse cx="158" cy="169" rx="91" ry="115" fill="url(#relief-glow)" opacity=".42"/>
        <use href="#relief-window" x="82" y="113" width="53" height="120"/>
        <use href="#relief-window" x="188" y="113" width="53" height="120"/>
        <use href="#relief-crystal" x="134" y="99" width="52" height="95"/>
        <path d="m92 245 66 11 81-13-7 13-73 13-67-13Z" fill="url(#relief-bevel)"/>
        <path d="m96 245 62 9 77-11" fill="none" stroke="#8195b0" stroke-opacity=".7"/>
        <path d="m143 283 15-8 15 8-15 10Z" fill="#17273d" stroke="#566b89"/><path d="m158 279-4 6 4 4 4-4Z" fill="#a5b9de" opacity=".5"/>
        <use href="#relief-column" x="29" y="0" width="50" height="320"/>
        <use href="#relief-column" x="247" y="0" width="50" height="320"/>
        <use href="#relief-cornice" width="320" height="90"/>
        <path d="m160 28 30 23-30 32-30-32Z" fill="#14263d" stroke="#7b90ad" stroke-width="2"/>
    </symbol>
    <symbol id="relief-merlon" viewBox="0 0 40 53">
        <path d="M1 8 11 1h21l7 8-8 6H1Z" fill="url(#relief-top)"/><path d="M1 8h27v45H1Z" fill="url(#relief-bevel)"/>
        <path d="m28 8 11 1v38l-11 6Z" fill="#15253b"/><path d="M4 11h21" stroke="#9baec9" stroke-opacity=".5"/>
    </symbol>
    <symbol id="tower-crown-relief" viewBox="0 0 320 210">
        <ellipse cx="160" cy="64" rx="116" ry="66" fill="url(#relief-glow)" opacity=".6"/>
        <use href="#relief-crystal" x="131" y="0" width="58" height="86"/>
        <path d="M31 102 58 84h212l24 20-19 35H48Z" fill="#101e33"/>
        <path d="M49 128h222v82H49Z" fill="url(#relief-front)"/><path d="M49 128h222v82H49Z" fill="url(#relief-stone)"/>
        <use href="#relief-merlon" x="28" y="63" width="40" height="53"/><use href="#relief-merlon" x="86" y="63" width="40" height="53"/>
        <use href="#relief-merlon" x="145" y="63" width="40" height="53"/><use href="#relief-merlon" x="204" y="63" width="40" height="53"/><use href="#relief-merlon" x="262" y="63" width="40" height="53"/>
        <path d="M29 108h269v18H29Z" fill="url(#relief-bevel)"/><path d="M31 110h263" stroke="#aabbd1" stroke-opacity=".5"/>
        <path d="M49 126h222v30H49Z" fill="url(#relief-shadow)"/>
        <path d="M78 149v34m41-34v34m41-34v34m41-34v34m41-34v34" stroke="#0a1527" stroke-width="14"/>
        <path d="M72 149v34m41-34v34m41-34v34m41-34v34m41-34v34" stroke="#778ba6" stroke-width="3"/>
        <path d="m26 192 12-8h242l14 8v18H26Z" fill="url(#relief-bevel)"/><path d="M28 194h263" stroke="#8195b1"/>
    </symbol>
    <symbol id="tower-base-relief" viewBox="0 0 420 300">
        <path d="M82 0h256l27 215H55Z" fill="url(#relief-front)"/><path d="M82 0h256l27 215H55Z" fill="url(#relief-stone)"/>
        <path d="M298 0h40l27 215h-67Z" fill="#0c1a2c" opacity=".65"/>
        <path d="M92 15h30l20 200H74ZM305 16l28 199h-31Z" fill="#041020" opacity=".55"/>
        <path d="M139 222V113q0-57 71-92 71 35 71 92v109Z" fill="url(#relief-bevel)" stroke="#7e91ac" stroke-width="2"/>
        <path d="M153 221V113q0-47 57-77 57 30 57 77v108Z" fill="#071426"/>
        <path d="M170 220V119q0-39 48-67 36 26 36 67v101Z" fill="url(#relief-glass)"/>
        <path d="M156 218V115q0-44 54-75l8 12q-48 28-48 67v101Z" fill="#0d1a2c"/>
        <path d="M184 93v127m20-145v145m20-160v160m20-132v132M170 144h84m-84 48h84" stroke="#13243c" stroke-width="5"/>
        <path d="m145 100 14 3m-7-29 13 8m1-27 11 10m5-26 8 12m16-29 2 14m17-12-3 15m20-2-8 11m20 4-10 9m18 10-12 7" stroke="#1b2b42" stroke-width="3"/>
        <path d="M41 216 63 30l21-12 25 11-19 188Z" fill="url(#relief-top)"/><path d="M41 216 63 30h27L71 216Z" fill="url(#relief-bevel)"/><path d="m90 30 19-1-19 188H71Z" fill="#1c2e45"/>
        <path d="M327 216 310 29l19-11 26 12 28 186Z" fill="url(#relief-top)"/><path d="M327 216 310 29h28l25 187Z" fill="url(#relief-bevel)"/><path d="m338 29 17 1 28 186h-20Z" fill="#17273e"/>
        <use href="#relief-crystal" x="88" y="85" width="48" height="94"/><use href="#relief-crystal" x="283" y="85" width="48" height="94"/>
        <path d="m52 211-13 15h338l-17-15Z" fill="url(#relief-top)"/><path d="M39 226h338v13H39Z" fill="url(#relief-bevel)"/>
        <path d="m39 239-17 11h373l-18-11Z" fill="url(#relief-top)"/><path d="M22 250h373v12H22Z" fill="url(#relief-bevel)"/>
        <path d="m22 262-16 12h407l-18-12Z" fill="url(#relief-top)"/><path d="M6 274h407v12H6Z" fill="url(#relief-bevel)"/>
        <path d="M7 287h405" stroke="#8ca4bf" stroke-opacity=".3"/>
    </symbol>
</defs></svg>
