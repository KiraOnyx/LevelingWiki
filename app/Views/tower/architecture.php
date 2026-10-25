<?php // Shared SVG definitions: one masonry pattern and a handful of modules for the whole tower. ?>
<svg class="tower-defs" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
<linearGradient id="stone-shade"><stop stop-color="#0c1322"/><stop offset=".24" stop-color="#424759"/><stop offset=".52" stop-color="#303648"/><stop offset=".8" stop-color="#272c40"/><stop offset="1" stop-color="#0a1020"/></linearGradient>
<linearGradient id="rim" x2="0" y2="1"><stop stop-color="#818092"/><stop offset=".16" stop-color="#4e5267"/><stop offset=".55" stop-color="#252a3d"/><stop offset="1" stop-color="#101725"/></linearGradient>
<linearGradient id="glass" x2="0" y2="1"><stop stop-color="#a9a1ff"/><stop offset=".45" stop-color="#6058b6"/><stop offset="1" stop-color="#171e3a"/></linearGradient>
<radialGradient id="aura"><stop stop-color="#8275df" stop-opacity=".5"/><stop offset="1" stop-color="#8275df" stop-opacity="0"/></radialGradient>
<pattern id="masonry" width="112" height="72" patternUnits="userSpaceOnUse">
<path d="M1 2 53 1 55 33 2 34Z" fill="#9295aa" fill-opacity=".09" stroke="#090e19" stroke-width="2"/>
<path d="M58 2 110 2 109 33 59 34Z" fill="#090e19" fill-opacity=".17" stroke="#090e19" stroke-width="2"/>
<path d="M-25 38 23 37 25 69-24 70M28 38 79 37 80 70 28 70ZM84 38 134 37 134 70 83 69Z" fill="#9394a6" fill-opacity=".05" stroke="#0b101d" stroke-width="2"/>
<path d="M4 4h46M61 5h45M31 40h44M86 40h23" stroke="#b4b8cb" stroke-opacity=".16"/>
<path d="m14 11 9 2m48 39 6 4m20-38 3 9" stroke="#090e19" stroke-opacity=".4"/>
</pattern>
<symbol id="gothic-window" viewBox="0 0 56 110">
<path d="M4 106V44Q5 20 28 4 51 20 52 44v62Z" fill="#0b1020" stroke="#626579" stroke-width="5"/>
<path d="M11 101V45Q11 27 28 13 45 27 45 45v56Z" fill="url(#glass)" stroke="#151b2c" stroke-width="3"/>
<path d="M28 16v85M11 55h34M13 54q3-19 15-22 12 3 15 22M11 79h34" fill="none" stroke="#1a2033" stroke-width="3"/>
<path d="M0 107h56" stroke="url(#rim)" stroke-width="6"/>
</symbol>
<symbol id="tower-floor" viewBox="0 0 320 320">
<path d="M33 0H287V320H33Z" fill="url(#stone-shade)"/><path d="M33 0H287V320H33Z" fill="url(#masonry)"/>
<path d="M31 0h26v320H31ZM263 0h26v320h-26Z" fill="url(#rim)"/>
<path d="M37 0v320m12-320v320M271 0v320m11-320v320" stroke="#6e7183" stroke-opacity=".3"/>
<path d="m21 271 21-13 23 13v22H21Zm234 0 22-13 22 13v22h-44Z" fill="url(#rim)" stroke="#151c2d"/>
<ellipse cx="160" cy="154" rx="103" ry="123" fill="url(#aura)" opacity=".35"/>
<use href="#gothic-window" x="83" y="119" width="47" height="99"/><use href="#gothic-window" x="190" y="119" width="47" height="99"/>
<path d="m160 108 10 19-10 28-10-28Z" fill="#a69af0" opacity=".85"/><path d="m160 108 0 47-10-28Z" fill="#62549f"/>
<path d="M67 236q93 36 186 0v12q-93 36-186 0Z" fill="url(#rim)"/>
<path d="M22 10h276v14H22ZM26 25h268v14H26Z" fill="url(#rim)" stroke="#151b2b"/>
<path d="M29 41h262l-13 14H42Z" fill="#141b2b"/><path d="M37 43v19m19-19v19m208-19v19m19-19v19" stroke="#4b5064" stroke-width="7"/>
<path d="m160 35 25 25-25 25-25-25Z" fill="#202637" stroke="#79718b"/>
</symbol>
<symbol id="tower-crown" viewBox="0 0 320 210">
<path d="m160 3 8 28-8 27-8-27Z" fill="#c4b4ff"/><ellipse cx="160" cy="41" rx="65" ry="40" fill="url(#aura)"/>
<path d="M32 112V63h30v22h24V63h30v22h29V63h30v22h29V63h30v22h24V63h30v49l-17 27v71H49v-71Z" fill="url(#stone-shade)" stroke="#78788c" stroke-width="2"/>
<path d="M49 136h222v74H49Z" fill="url(#masonry)"/><path d="M23 110h274v14H23Zm13 17h248v12H36Z" fill="url(#rim)"/>
<path d="M74 146v44m43-44v44m43-44v44m43-44v44m43-44v44" stroke="#0a1120" stroke-width="11"/>
<path d="M28 192h264v18H28Z" fill="url(#rim)"/>
</symbol>
<symbol id="tower-base" viewBox="0 0 420 300">
<path d="M82 0h256l27 215H55Z" fill="url(#stone-shade)"/><path d="M82 0h256l27 215H55Z" fill="url(#masonry)"/>
<path d="M36 215 62 30h37L83 215Zm301 0L321 30h37l26 185Z" fill="url(#rim)"/>
<path d="M143 221V112q0-56 67-89 67 33 67 89v109Z" fill="#101626" stroke="#676778" stroke-width="13"/>
<path d="M158 219V112q0-43 52-72 52 29 52 72v107Z" fill="url(#glass)"/>
<path d="M173 82v137m18-160v160m19-174v174m19-160v160m18-137v137M157 135h105m-105 51h105" stroke="#101827" stroke-width="5"/>
<path d="M42 218h336v17H42Zm-17 19h370v18H25ZM8 257h404v20H8Z" fill="url(#rim)" stroke="#101726" stroke-width="2"/>
<path d="m111 114 9 16-9 23-9-23Zm198 0 9 16-9 23-9-23Z" fill="#aaa3ff"/>
<path d="M14 280h392" stroke="#747083" stroke-opacity=".5"/>
</symbol>
</defs></svg>
