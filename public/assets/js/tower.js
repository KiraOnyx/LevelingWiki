import {levelPosition, placeLabel} from './tower-geometry.mjs';
import {installRenderer} from './tower-renderer.mjs?v=phase-b-1';

const root = document.querySelector('[data-tower-prototype]');
if (root) {
    const fixture = JSON.parse(document.querySelector('#tower-data').textContent);
    let divisions = fixture.divisions.map(d => ({...d}));
    const live = root.dataset.live === 'true';
    const accent = (element, color) => { if (/^#[0-9a-f]{6}$/i.test(color || '')) element.style.setProperty('--division-color',color); };
    root.querySelectorAll('[data-division-color]').forEach(el=>accent(el,el.dataset.divisionColor));
    const stage = root.querySelector('.tower-stage');
    const floors = root.querySelector('.tower-floors');
    const markers = root.querySelector('.tower-markers');
    const connectors = root.querySelector('.tower-connectors');
    const navigation = root.querySelector('.tower-navigation');
    const mobile = matchMedia('(max-width:600px)');
    const reduce = matchMedia('(prefers-reduced-motion:reduce)');
    const motionReduced = () => reduce.matches || new URLSearchParams(location.search).get('motion') === 'reduce';
    const svgNS = 'http://www.w3.org/2000/svg';
    let anchors = [], feedbackTimer, layoutFrame, interacted = false;
    const notice = message => {
        root.querySelector('.tower-feedback').textContent = message;
        clearTimeout(feedbackTimer);
        feedbackTimer = setTimeout(() => { root.querySelector('.tower-feedback').textContent = ''; }, 5000);
    };
    const applyRenderer = installRenderer(root, {mobile, reduce, announce:notice});
    function updateRewards() {
        for (const floor of floors.children) {
            const reward = floor.querySelector('.tower-reward');
            if (!reward) continue;
            const level = Number(floor.dataset.level);
            const unlocked = divisions.filter(d => d.level >= level);
            reward.classList.toggle('is-unlocked', unlocked.length > 0);
            reward.querySelector('.reward-availability').textContent = unlocked.length ? '✓' : '◇';
            const list = reward.querySelector('.tower-unlocks');
            list.replaceChildren(...divisions.map(d => {
                const row = document.createElement('span');
                row.textContent = `${d.name} · ${d.level >= level ? 'palier atteint' : `encore ${level - d.level} niveaux`}`;
                return row;
            }));
        }
    }
    function draw() {
        // Expanded details must reserve their own floor height, especially on small screens.
        const baseline = parseFloat(getComputedStyle(root).getPropertyValue('--floor-height'));
        for (const floor of floors.children) {
            floor.style.height = `${Math.max(baseline, (floor.querySelector('.tower-reward')?.offsetHeight || 0) + (parseFloat(getComputedStyle(floor.querySelector('.tower-reward') || floor).top) || 0) + 40)}px`;
        }
        const box = stage.getBoundingClientRect();
        const mobileView = mobile.matches;
        const headerHeight = document.querySelector('.site-header').getBoundingClientRect().height;
        root.style.setProperty('--tower-header-height', `${headerHeight}px`);
        if (mobileView) root.style.setProperty('--tower-nav-top', `${headerHeight - 56}px`);
        const towerWidth = parseFloat(getComputedStyle(root).getPropertyValue('--tower-width'));
        const center = box.width / 2;
        const floorNodes = [...floors.children];
        anchors = floorNodes.map(floor => {
            const medal = floor.querySelector('.floor-medallion').getBoundingClientRect();
            return {level:Number(floor.dataset.level), y:medal.top - box.top + medal.height / 2};
        });
        const occupied = [[], []];
        for (const floor of floorNodes) {
            const card = floor.querySelector('.tower-reward');
            if (card) {
                const r = card.getBoundingClientRect();
                const side = mobileView || floor.classList.contains('reward-right') ? 1 : 0;
                const block = {start: r.top - box.top - 8, end: r.bottom - box.top + 8};
                occupied[side].push(block);
                if(mobileView)occupied[0].push(block);
            }
            if (mobileView) {
                const y = floor.getBoundingClientRect().top - box.top;
                occupied[0].push({start:y + 18, end:y + 94});
                occupied[1].push({start:y + 18, end:y + 94});
            }
        }
        let lowest = 0;
        connectors.replaceChildren();
        const sorted = [...divisions].sort((a,b) => b.level - a.level);
        for (const division of sorted) {
            const marker = document.getElementById(`division-${division.id}`);
            const targetY = levelPosition(division.level, anchors);
            const height = marker.offsetHeight;
            const width = marker.offsetWidth;
            const leftY = placeLabel(targetY, height, occupied[0], 0);
            const rightY = placeLabel(targetY, height, occupied[1], 0);
            const side = Math.abs(leftY + height / 2 - targetY) <= Math.abs(rightY + height / 2 - targetY) ? 0 : 1;
            const y = side ? rightY : leftY;
            occupied[side].push({start:y,end:y + height});
            const x = side ? box.width - width : 0;
            marker.style.left = `${x}px`;
            marker.style.top = `${y}px`;
            marker.dataset.realY = String(targetY);
            const edge = center + (side ? 1 : -1) * towerWidth * .39;
            const endpoint = side ? x : x + width;
            const elbow = mobileView ? (side ? box.width-5 : 5) : (edge + endpoint) / 2;
            const line = document.createElementNS(svgNS, 'path');
            line.setAttribute('d', `M ${edge} ${targetY} H ${elbow} V ${y + height / 2} H ${endpoint}`);
            line.setAttribute('class', `division-${division.color}`);
            const dot = document.createElementNS(svgNS, 'circle');
            dot.setAttribute('cx', String(edge)); dot.setAttribute('cy', String(targetY)); dot.setAttribute('r', '4');
            dot.setAttribute('class', `division-${division.color}`);
            accent(line,division.accent);accent(dot,division.accent);
            connectors.append(line, dot);
            lowest = Math.max(lowest, y + height);
        }
        const naturalBottom = root.querySelector('.tower-foundation').getBoundingClientRect().bottom - box.top;
        stage.style.paddingBottom = `${Math.max(0, lowest - naturalBottom + 20)}px`;
    }
    function queueDraw() {
        cancelAnimationFrame(layoutFrame);
        layoutFrame = requestAnimationFrame(draw);
    }
    function buildMarkers() {
        markers.replaceChildren(...divisions.map(d => {
            const button = document.createElement('button');
            button.type = 'button'; button.className = `tower-marker division-${d.color}`;
            button.id = `division-${d.id}`;accent(button,d.accent);
            button.setAttribute('aria-label', `${d.name}, niveau ${d.level}. Afficher sa progression.`);
            const title = document.createElement('strong');
            const full = document.createElement('span'); full.className = 'marker-full'; full.textContent = d.name;
            const short = document.createElement('span'); short.className = 'marker-short'; short.textContent = `${d.short} · Niv. ${d.level}`;
            title.append(full, short);
            const subtitle = document.createElement('small'); subtitle.textContent = `Niveau ${d.level}${live ? '' : ' · division fictive'}`;
            button.append(title, subtitle);
            button.addEventListener('click', () => {
                const count = fixture.levels.filter(l => l.level <= d.level).length;
                notice(`${d.name} · niveau ${d.level} · ${count} paliers atteints.${live ? '' : ' Progression fictive.'}`);
            });
            return button;
        }));
        updateRewards();
        draw();
    }
    function highlight(element) {
        root.querySelectorAll('.is-highlighted').forEach(el => el.classList.remove('is-highlighted'));
        element.classList.add('is-highlighted');
        setTimeout(() => element.classList.remove('is-highlighted'), 2200);
    }
    function goTo(id, smooth = true) {
        const element = document.getElementById(id);
        if (!element || !root.contains(element)) return;
        element.scrollIntoView({behavior:smooth && !motionReduced() ? 'smooth' : 'instant', block:'center'});
        if (id.startsWith('division-')) highlight(element);
    }
    root.addEventListener('click', event => {
        const link = event.target.closest('a[href^="#"]');
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button > 0) return;
        const id = link.getAttribute('href').slice(1);
        if (!document.getElementById(id)) return;
        event.preventDefault();
        history.pushState(null, '', `#${id}`);
        if (mobile.matches) navigation.open = false;
        goTo(id);
        document.getElementById(id).focus({preventScroll:true});
    });
    root.querySelectorAll('[data-reward-detail]').forEach(button => {
        button.setAttribute('aria-expanded','false');
        button.addEventListener('click', () => {
            const list = button.nextElementSibling;
            list.hidden = !list.hidden;
            button.setAttribute('aria-expanded',String(!list.hidden));
            queueDraw();
        });
    });
    root.querySelector('#tower-scenario')?.addEventListener('change', event => {
        // Only temporary demo geometry changes. Existing reward interactions remain attached.
        floors.querySelector('[data-extension]')?.remove();
        if (event.target.value === 'collision') {
            divisions = Array.from({length:8}, (_,i) => ({id:`v${i+1}`, name:`Valhalla ${i+1}`, short:`V${i+1}`, level:43-Math.floor(i/3), color:['violet','blue','teal'][i%3]}));
        } else if (event.target.value === 'extended') {
            divisions = fixture.divisions.map((d,i) => ({...d,level:[135,100,1][i]}));
            const extension = document.createElement('section');
            extension.className = 'tower-floor'; extension.dataset.level = '135'; extension.dataset.extension = '';
            extension.id = 'level-135'; extension.tabIndex = -1; extension.setAttribute('aria-label','Niveau 135 : au-delà du dernier palier connu, aucune récompense ajoutée');
            extension.innerHTML = '<svg class="floor-architecture" viewBox="0 0 320 320" preserveAspectRatio="none" aria-hidden="true"><use data-architecture="floor" href="#tower-floor"/></svg><div class="floor-medallion"><small>NIV.</small><strong>135</strong></div>';
            floors.prepend(extension);
        } else if (event.target.value === 'edges') {
            divisions = fixture.divisions.map((d,i) => ({...d,level:[1,100,1][i]}));
        } else divisions = fixture.divisions.map(d => ({...d}));
        // Keep all displayed comparison/navigation values in sync with the scenario.
        const links = root.querySelector('.tower-navigation nav');
        links.querySelectorAll('[class^="division-"]').forEach(el => el.remove());
        const bottomLink = links.querySelector('[href="#level-1"]');
        for (const d of divisions) {
            const link = document.createElement('a'); link.className = `division-${d.color}`; link.href = `#division-${d.id}`;
            const dot = document.createElement('i'); const value = document.createElement('small'); value.textContent = String(d.level);
            link.append(dot,document.createTextNode(d.name),value); links.insertBefore(link,bottomLink);
        }
        const leader = Math.max(...divisions.map(d=>d.level));
        root.querySelector('.tower-ranking ol').replaceChildren(...[...divisions].sort((a,b)=>b.level-a.level).map((d,i)=>{
            const li = document.createElement('li'); li.className=`division-${d.color}`;
            const rank = document.createElement('span'); rank.className='division-rank'; rank.textContent=String(i+1);
            const link = document.createElement('a'); link.href=`#division-${d.id}`; link.textContent=d.name;
            const gap = document.createElement('small'); gap.textContent=d.level===leader?'En tête':`${leader-d.level} niveaux derrière`; link.append(gap);
            const value = document.createElement('strong'); value.textContent=String(d.level); li.append(rank,link,value); return li;
        }));
        const stats = root.querySelectorAll('.tower-summary strong'); stats[0].textContent=String(divisions.length); stats[2].textContent=String(leader);
        root.querySelector('[href="#tower-top"] small').textContent=`Niv. ${Math.max(...fixture.levels.map(l=>l.level),leader)}`;
        applyRenderer();
        buildMarkers();
        notice('Scénario fictif chargé. Aucune donnée enregistrée.');
    });
    if(live && divisions.length) {
        const maximum = Math.max(...divisions.map(d=>d.level));
        const known = Math.max(...fixture.levels.map(l=>l.level));
        if(maximum > known) {
            const extension = document.createElement('section');
            extension.className='tower-floor';extension.dataset.level=String(maximum);extension.id=`level-${maximum}`;extension.tabIndex=-1;
            extension.setAttribute('aria-label',`Niveau ${maximum}, au-delà du dernier palier connu`);
            extension.innerHTML='<svg class="floor-architecture" viewBox="0 0 320 320" preserveAspectRatio="none" aria-hidden="true"><use data-architecture="floor" href="#tower-floor"/></svg><div class="floor-medallion"><small>NIV.</small><strong></strong></div>';
            extension.querySelector('strong').textContent=String(maximum);
            if(String(maximum).length>4)extension.querySelector('.floor-medallion').classList.add('long-level');
            floors.prepend(extension);applyRenderer();
            root.querySelector('[href="#tower-top"] small').textContent=`Niv. ${maximum}`;
        }
    }
    navigation.open = !mobile.matches;
    mobile.addEventListener('change', () => {navigation.open = !mobile.matches;queueDraw();});
    const layoutObserver = new ResizeObserver(queueDraw);
    layoutObserver.observe(stage);layoutObserver.observe(document.querySelector('.site-header'));
    window.addEventListener('keydown', event => {if(event.key==='Escape' && mobile.matches && navigation.open){navigation.open=false;navigation.querySelector('summary').focus();}});
    document.addEventListener('pointerdown',event=>{if(mobile.matches && navigation.open && !navigation.contains(event.target))navigation.open=false;});
    window.addEventListener('resize', queueDraw, {passive:true});
    // Browser history owns back/forward scroll restoration; do not intercept popstate/hashchange.
    for (const type of ['wheel','touchstart','pointerdown','keydown']) {
        window.addEventListener(type, () => {interacted = true;}, {once:true,passive:true});
    }
    buildMarkers();
    // One initial positioning only. Reload/back-forward restore browser position normally.
    const navigationType = performance.getEntriesByType('navigation')[0]?.type;
    const ready = () => requestAnimationFrame(() => requestAnimationFrame(() => {
        draw();
        if (navigationType === 'back_forward' || navigationType === 'reload') return;
        if (location.hash) {goTo(decodeURIComponent(location.hash.slice(1)),false);return;}
        if (interacted) return;
        const foot = document.getElementById('level-1');
        if (motionReduced()) {foot.scrollIntoView({block:'center',behavior:'instant'});return;}
        const target = foot.getBoundingClientRect().top + scrollY - innerHeight/2 + 90;
        // Bound the visible arrival to 120px, regardless of the height of the tower.
        window.scrollTo({top:Math.max(0,target-120),behavior:'instant'});
        window.scrollTo({top:Math.max(0,target),behavior:'smooth'});
    }));
    if (document.readyState === 'complete') ready(); else window.addEventListener('load',ready,{once:true});
}
