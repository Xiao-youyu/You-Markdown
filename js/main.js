

(function() {
    const topBar = document.getElementById('topBar');
    const btnSearch = document.getElementById('btnSearch');
    const btnToc = document.getElementById('btnToc');
    const btnFont = document.getElementById('btnFont');
    const btnThemeToggle = document.getElementById('btnThemeToggle');
    const btnColor = document.getElementById('btnColor');
    const searchPanel = document.getElementById('searchPanel');
    const tocPanel = document.getElementById('tocPanel');
    const fontPanel = document.getElementById('fontPanel');
    const colorPanel = document.getElementById('colorPanel');
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const tocFileList = document.getElementById('tocFileList');
    const homeView = document.getElementById('homeView');
    const cardsGrid = document.getElementById('cardsGrid');
    const emptyHome = document.getElementById('emptyHome');
    const readingView = document.getElementById('readingView');
    const markdownBody = document.getElementById('markdownBody');
    const floatingButtons = document.getElementById('floatingButtons');
    const floatTocBtn = document.getElementById('floatTocBtn');
    const scrollToTopBtn = document.getElementById('scrollToTopBtn');
    const floatHomeBtn = document.getElementById('floatHomeBtn');
    const tocPopup = document.getElementById('tocPopup');
    const tocPopupList = document.getElementById('tocPopupList');
    const hueSlider = document.getElementById('hueSlider');
    const fontTypeButtons = document.querySelectorAll('.font-type-btn');
    const fontSizeSlider = document.getElementById('fontSizeSlider');
    const fontSizeValue = document.getElementById('fontSizeValue');
    const tocPanelHeader = document.getElementById('tocPanelHeader');
    const shareModalOverlay = document.getElementById('shareModalOverlay');
    const shareQrcode = document.getElementById('shareQrcode');
    const shareModalClose = document.getElementById('shareModalClose');
    const readingProgress = document.getElementById('readingProgress');
    const toast = document.getElementById('toast');
    const imgLightbox = document.getElementById('imgLightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    const sidebar = document.getElementById('sidebar');
    const sidebarFileList = document.getElementById('sidebarFileList');
    const sidebarTocList = document.getElementById('sidebarTocList');
    const sidebarTocHeader = document.getElementById('sidebarTocHeader');
    const sidebarSearchInput = document.getElementById('sidebarSearchInput');
    const sidebarCount = document.getElementById('sidebarCount');
    const sidebarBackBtn = document.getElementById('sidebarBackBtn');
    const sidebarArticleTitle = document.getElementById('sidebarArticleTitle');
    const prevNextNav = document.getElementById('prevNextNav');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const prevTitle = document.getElementById('prevTitle');
    const nextTitle = document.getElementById('nextTitle');

    let allFiles = [];
    let isReadingView = false;
    let currentHeadings = [];
    let activeHeadingIndex = -1;
    let lastScrollY = 0;
    let currentFileIndex = -1;
    let currentFileName = '';

    function escapeHTML(str) { const div = document.createElement('div'); div.textContent = str; return div.innerHTML; }

    function setAccentHue(hue) {
        document.documentElement.style.setProperty('--accent-hue', hue);
        localStorage.setItem('md-reader-hue', hue);
    }
    const savedHue = localStorage.getItem('md-reader-hue') || 220;
    setAccentHue(savedHue);
    hueSlider.value = savedHue;
    hueSlider.addEventListener('input', () => setAccentHue(hueSlider.value));

    function applyFontType(type) {
        if (type === 'default') {
            document.body.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif';
        } else {
            document.body.style.fontFamily = "'EnglishFont', 'ChineseFont', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif";
        }
        localStorage.setItem('md-font-type', type);
    }
    function applyFontSize(size) {
        document.documentElement.style.setProperty('--base-font-size', size + 'px');
        fontSizeValue.textContent = size + 'px';
        localStorage.setItem('md-font-size', size);
    }

    const savedFontType = localStorage.getItem('md-font-type') || 'default';
    const savedFontSize = localStorage.getItem('md-font-size') || 16;
    applyFontType(savedFontType);
    applyFontSize(savedFontSize);
    fontSizeSlider.value = savedFontSize;
    fontTypeButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.font === savedFontType));
    fontTypeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            fontTypeButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            applyFontType(btn.dataset.font);
        });
    });
    fontSizeSlider.addEventListener('input', () => applyFontSize(fontSizeSlider.value));

    function openPanel(panel) { closeAllPanels(); panel.classList.add('active'); }
    function closePanel(panel) { panel.classList.remove('active'); }
    function closeAllPanels() {
        [searchPanel, tocPanel, fontPanel, colorPanel].forEach(p => p.classList.remove('active'));
    }

    btnSearch.addEventListener('click', (e) => {
        e.stopPropagation();
        if (searchPanel.classList.contains('active')) closePanel(searchPanel);
        else openPanel(searchPanel);
    });
    btnToc.addEventListener('click', (e) => {
        e.stopPropagation();
        if (tocPanel.classList.contains('active')) closePanel(tocPanel);
        else {
            if (isReadingView) { renderDocumentOutline(); tocPanelHeader.style.display = 'block'; }
            else { renderTocList(); tocPanelHeader.style.display = 'none'; }
            openPanel(tocPanel);
        }
    });
    btnFont.addEventListener('click', (e) => {
        e.stopPropagation();
        if (fontPanel.classList.contains('active')) closePanel(fontPanel);
        else openPanel(fontPanel);
    });
    btnColor.addEventListener('click', (e) => {
        e.stopPropagation();
        if (colorPanel.classList.contains('active')) closePanel(colorPanel);
        else openPanel(colorPanel);
    });

    document.addEventListener('click', (e) => {
        if (!searchPanel.contains(e.target) && e.target !== btnSearch) closePanel(searchPanel);
        if (!tocPanel.contains(e.target) && e.target !== btnToc) closePanel(tocPanel);
        if (!fontPanel.contains(e.target) && e.target !== btnFont) closePanel(fontPanel);
        if (!colorPanel.contains(e.target) && e.target !== btnColor) closePanel(colorPanel);
    });

    floatTocBtn.addEventListener('click', (e) => { e.stopPropagation(); tocPopup.classList.toggle('active'); });
    document.addEventListener('click', (e) => { if (!tocPopup.contains(e.target) && !floatTocBtn.contains(e.target)) tocPopup.classList.remove('active'); });

    shareModalClose.addEventListener('click', () => { shareModalOverlay.classList.remove('active'); shareQrcode.innerHTML = ''; });
    shareModalOverlay.addEventListener('click', (e) => { if (e.target === shareModalOverlay) { shareModalOverlay.classList.remove('active'); shareQrcode.innerHTML = ''; } });

    window.addEventListener('scroll', () => {
        const scrollY = window.pageYOffset || document.documentElement.scrollTop;
        scrollToTopBtn.style.opacity = scrollY > 300 ? '1' : '0';
        scrollToTopBtn.style.pointerEvents = scrollY > 300 ? 'auto' : 'none';
        if (isReadingView) {
            if (scrollY <= 0) topBar.classList.remove('hidden');
            else if (scrollY > lastScrollY && scrollY > 80) topBar.classList.add('hidden');
            else if (scrollY < lastScrollY) topBar.classList.remove('hidden');
            updateActiveHeading();
            const docH = document.documentElement.scrollHeight - window.innerHeight;
            const pct = docH > 0 ? Math.min(100, (scrollY / docH) * 100) : 0;
            const isDesktop = window.innerWidth >= 1025;
            if (isDesktop) {
                const barW = window.innerWidth - 280;
                readingProgress.style.width = (barW * pct / 100) + 'px';
            } else {
                readingProgress.style.width = pct + '%';
            }
            readingProgress.classList.add('active');
        } else {
            topBar.classList.remove('hidden');
            readingProgress.classList.remove('active');
            readingProgress.style.width = '0%';
        }
        lastScrollY = scrollY;
    });
    scrollToTopBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    floatHomeBtn.addEventListener('click', showHome);

    btnThemeToggle.addEventListener('click', () => {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        document.documentElement.setAttribute('data-theme', isDark ? 'light' : 'dark');
        localStorage.setItem('md-theme', isDark ? 'light' : 'dark');
        btnThemeToggle.innerHTML = isDark
            ? '<svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>'
            : '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
    });

    (function() {
        const saved = localStorage.getItem('md-theme') || 'light';
        document.documentElement.setAttribute('data-theme', saved);
        if (saved === 'dark') {
            btnThemeToggle.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>';
        }
    })();

    async function loadFileList() {
        try {
            const resp = await fetch('?action=list');
            const data = await resp.json();
            if (data.success) { allFiles = data.files; renderCards(); renderSidebarList(allFiles); }
        } catch (err) { cardsGrid.innerHTML = '<div class="empty-state">⚠️ 加载失败</div>'; }
    }

    function renderCards() {
        if (allFiles.length === 0) { emptyHome.style.display = 'block'; cardsGrid.innerHTML = ''; return; }
        emptyHome.style.display = 'none';
        cardsGrid.innerHTML = allFiles.map((file, i) => `
            <div class="doc-card" data-filename="${escapeHTML(file.name)}" style="animation-delay:${i*0.05}s">
                <div class="card-title">${file.pinned ? '<span class="card-pin-icon" title="置顶"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2z" fill="currentColor" stroke="none"/></svg></span>' : ''}${escapeHTML(file.displayName)}</div>
                <div class="card-meta">
                    <span><span class="meta-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>${file.modified}</span>
                    <span><span class="meta-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>${file.wordCount}字</span>
                    ${file.category ? `<span><span class="meta-icon"><svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>${escapeHTML(file.category)}</span>` : ''}
                </div>
                <div class="card-excerpt">${escapeHTML(file.excerpt || '')}</div>
                <div class="card-tags">${file.tags.map(t => `<span class="tag">#${escapeHTML(t)}</span>`).join('')}</div>
            </div>`).join('');
        document.querySelectorAll('.doc-card').forEach(card => card.addEventListener('click', () => loadFile(card.dataset.filename)));
    }

    function renderSidebarList(files) {
        if (!sidebarFileList) return;
        sidebarCount.textContent = files.length;
        sidebarFileList.innerHTML = files.map(file => `
            <div class="sidebar-item" data-filename="${escapeHTML(file.name)}">
                <div class="sidebar-item-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <div style="flex:1;min-width:0;overflow:hidden;">
                    <div class="sidebar-item-text">${file.pinned ? '<span class="sidebar-pin-icon" title="置顶"><svg viewBox="0 0 24 24" width="14" height="14"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2z" fill="currentColor" stroke="none"/></svg></span>' : ''}${escapeHTML(file.displayName)}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${file.modified}</div>
                </div>
            </div>`).join('');
        sidebarFileList.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', () => loadFile(item.dataset.filename));
        });
    }
    function highlightSidebarItem(filename) {
        if (!sidebarFileList) return;
        sidebarFileList.querySelectorAll('.sidebar-item').forEach(item => {
            item.classList.toggle('active', item.dataset.filename === filename);
        });
    }
    function showSidebarToc() {
        if (!sidebarFileList || !sidebarTocList || !sidebarTocHeader) return;
        sidebarFileList.style.display = 'none';
        sidebarTocHeader.style.display = 'block';
        sidebarTocList.style.display = 'block';
        if (sidebarSearchInput) sidebarSearchInput.parentElement.style.display = 'none';
        if (sidebarCount) sidebarCount.style.display = 'none';
        if (sidebarBackBtn) sidebarBackBtn.style.display = 'flex';
        if (sidebarArticleTitle) sidebarArticleTitle.style.display = 'block';
    }
    function showSidebarFileList() {
        if (!sidebarFileList || !sidebarTocList || !sidebarTocHeader) return;
        sidebarFileList.style.display = 'block';
        sidebarTocHeader.style.display = 'none';
        sidebarTocList.style.display = 'none';
        if (sidebarSearchInput) sidebarSearchInput.parentElement.style.display = 'block';
        if (sidebarCount) sidebarCount.style.display = 'inline';
        if (sidebarBackBtn) sidebarBackBtn.style.display = 'none';
        if (sidebarArticleTitle) sidebarArticleTitle.style.display = 'none';
    }
    function renderSidebarToc(headings) {
        if (!sidebarTocList) return;
        if (!headings.length) { sidebarTocList.innerHTML = '<div style="padding:16px;color:var(--text-muted);text-align:center;font-size:13px;">暂无标题</div>'; return; }
        renderTocItems(sidebarTocList, headings);
    }
    if (sidebarSearchInput) {
        sidebarSearchInput.addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            if (!q) { renderSidebarList(allFiles); return; }
            const filtered = allFiles.filter(f =>
                f.displayName.toLowerCase().includes(q) || f.excerpt.toLowerCase().includes(q) || f.tags.some(t => t.toLowerCase().includes(q))
            );
            renderSidebarList(filtered);
        });
    }
    if (sidebarBackBtn) {
        sidebarBackBtn.addEventListener('click', showHome);
    }

    searchInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        if (!query) { searchResults.innerHTML = ''; return; }
        const filtered = allFiles.filter(f =>
            f.displayName.toLowerCase().includes(query) ||
            f.excerpt.toLowerCase().includes(query) ||
            f.tags.some(t => t.toLowerCase().includes(query))
        );
        searchResults.innerHTML = filtered.map(file => `
            <div class="dropdown-item" data-filename="${escapeHTML(file.name)}">
                <div class="file-icon"><svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <div class="file-info"><div class="file-name">${escapeHTML(file.displayName)}</div></div>
            </div>`).join('');
        document.querySelectorAll('#searchResults .dropdown-item').forEach(item => {
            item.addEventListener('click', () => {
                loadFile(item.dataset.filename);
                closePanel(searchPanel); searchInput.value = ''; searchResults.innerHTML = '';
            });
        });
    });

    function renderTocList() {
        tocFileList.innerHTML = allFiles.map(file => `
            <div class="dropdown-item" data-filename="${escapeHTML(file.name)}">
                <div class="file-icon"><svg width="16" height="16" viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                <div class="file-info"><div class="file-name">${escapeHTML(file.displayName)}</div></div>
            </div>`).join('');
        document.querySelectorAll('#tocFileList .dropdown-item').forEach(item => item.addEventListener('click', () => { loadFile(item.dataset.filename); closePanel(tocPanel); }));
    }

    function removeOrdinal(text) { return text.replace(/^[\d一二三四五六七八九十]+[\.\、\s]+/, ''); }

    function renderTocItems(container, headings) {
        container.innerHTML = headings.map((h, idx) => {
            const level = h.level;
            const leftIcon = level === 1
                ? `<div class="toc-block"><svg width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" fill="none"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>`
                : `<div class="toc-dot-circle"></div>`;
            return `<div class="toc-item" data-anchor="${h.el.id}" data-level="${level}" style="padding-left:${(level-1)*16}px">${leftIcon}<div class="toc-text">${escapeHTML(removeOrdinal(h.text))}</div></div>`;
        }).join('');
        container.querySelectorAll('.toc-item').forEach(item => item.addEventListener('click', function(e) {
            e.preventDefault();
            const anchor = this.dataset.anchor;
            if (anchor) { const target = document.getElementById(anchor); if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            closePanel(tocPanel); tocPopup.classList.remove('active');
        }));
    }

    function renderDocumentOutline() {
        if (!currentHeadings.length) { tocFileList.innerHTML = '<div style="padding:16px;color:var(--text-muted);text-align:center;">暂无标题</div>'; return; }
        renderTocItems(tocFileList, currentHeadings);
        updateActiveHeading(true);
    }

    function renderTocPopup() {
        if (!currentHeadings.length) { tocPopupList.innerHTML = '<div style="padding:16px;color:var(--text-muted);text-align:center;">暂无标题</div>'; return; }
        renderTocItems(tocPopupList, currentHeadings);
        updateActiveHeading(true);
    }

    function extractHeadings() {
        currentHeadings = [];
        markdownBody.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach((el, i) => {
            if (!el.id) {
                el.id = 'heading-' + i;
            }
            const id = el.id;
            currentHeadings.push({ text: el.textContent, level: parseInt(el.tagName.charAt(1)), el });
            const anchor = document.createElement('a');
            anchor.className = 'heading-anchor';
            anchor.href = '#' + id;
            anchor.textContent = '#';
            anchor.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                history.replaceState(null, '', '#' + id);
            });
            el.appendChild(anchor);
        });
    }

    function updateActiveHeading(force = false) {
        if (!currentHeadings.length) return;
        let activeIdx = -1;
        const scrollPos = window.scrollY + 80;
        for (let i = currentHeadings.length - 1; i >= 0; i--) { if (currentHeadings[i].el.offsetTop <= scrollPos) { activeIdx = i; break; } }
        if (activeIdx !== activeHeadingIndex || force) {
            activeHeadingIndex = activeIdx;
            const applyActive = (items) => items.forEach((item, i) => item.classList.toggle('active', i === activeIdx));
            applyActive(tocFileList.querySelectorAll('.toc-item'));
            applyActive(tocPopupList.querySelectorAll('.toc-item'));
            applyActive(sidebarTocList.querySelectorAll('.toc-item'));
        }
    }

    function addCopyButtons() {
        document.querySelectorAll('.markdown-body pre').forEach(pre => {
            if (pre.querySelector('.copy-btn')) return;
            const code = pre.querySelector('code');
            if (!code) return;

            const html = code.innerHTML;
            const rawLines = html.replace(/^\n/, '').replace(/\n$/, '').split('\n');
            while (rawLines.length > 0 && rawLines[rawLines.length - 1].trim() === '') rawLines.pop();
            code.innerHTML = rawLines.map(line => `<span class="line">${line || '\u00a0'}</span>`).join('\n');

            const btn = document.createElement('div');
            btn.className = 'copy-btn';
            btn.innerHTML = '<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const codeEl = pre.querySelector('code');
                navigator.clipboard.writeText(codeEl ? codeEl.textContent : pre.textContent).then(() => {
                    btn.classList.add('copied');
                    btn.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
                    showToast('已复制到剪贴板');
                    setTimeout(() => { btn.classList.remove('copied'); btn.innerHTML = '<svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'; }, 2000);
                });
            });
            pre.appendChild(btn);

            const lineCount = rawLines.length;
            if (lineCount > 15) {
                pre.classList.add('collapsible');
                const collapseBtn = document.createElement('div');
                collapseBtn.className = 'collapse-btn';
                collapseBtn.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>';
                collapseBtn.title = '折叠/展开代码块';
                collapseBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    pre.classList.toggle('collapsed');
                });
                pre.appendChild(collapseBtn);
            }
        });
    }

    function updatePrevNext() {
        if (!isReadingView || allFiles.length === 0 || currentFileIndex === -1) { prevNextNav.style.display = 'none'; return; }
        prevNextNav.style.display = 'flex';
        const prevIdx = currentFileIndex - 1;
        const nextIdx = currentFileIndex + 1;
        if (prevIdx >= 0) { prevBtn.classList.remove('disabled'); prevTitle.textContent = allFiles[prevIdx].displayName; }
        else { prevBtn.classList.add('disabled'); prevTitle.textContent = '没有了'; }
        if (nextIdx < allFiles.length) { nextBtn.classList.remove('disabled'); nextTitle.textContent = allFiles[nextIdx].displayName; }
        else { nextBtn.classList.add('disabled'); nextTitle.textContent = '没有了'; }
    }

    prevBtn.addEventListener('click', () => { if (currentFileIndex > 0) loadFile(allFiles[currentFileIndex - 1].name); });
    nextBtn.addEventListener('click', () => { if (currentFileIndex < allFiles.length - 1) loadFile(allFiles[currentFileIndex + 1].name); });

    function getUrlParam(name) { return new URL(window.location.href).searchParams.get(name); }
    function updateUrl(filename) {
        const url = new URL(window.location.href);
        if (filename) url.searchParams.set('file', filename);
        else url.searchParams.delete('file');
        window.history.pushState({}, '', url.toString());
    }

    function getShortUrl(filename) {
        const url = new URL(window.location.origin + window.location.pathname);
        url.searchParams.set('file', filename);
        return url.toString();
    }

    function showHome(pushState = true) {
        homeView.style.display = 'block'; readingView.classList.remove('active');
        isReadingView = false; floatingButtons.style.display = 'none'; prevNextNav.style.display = 'none';
        tocPopup.classList.remove('active'); shareModalOverlay.classList.remove('active'); shareQrcode.innerHTML = '';
        showSidebarFileList(); highlightSidebarItem('');
        document.title = 'You Markdown';
        window.scrollTo(0, 0);
        if (pushState) updateUrl(null);
    }

    function showReading() {
        homeView.style.display = 'none'; readingView.classList.add('active');
        isReadingView = true; floatingButtons.style.display = 'flex';
        showSidebarToc();
        window.scrollTo(0, 0);
    }

    function buildDocHeader(file) {
        const wordCount = file.wordCount;
        const readTime = Math.max(1, Math.round(wordCount / 300));
        let html = '<div class="doc-header">';
        html += '<div class="doc-stats">';
        html += '<span class="stat-item"><span class="meta-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>' + wordCount + ' 字</span>';
        html += '<span class="stat-item"><span class="meta-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>' + readTime + ' 分钟</span>';
        html += '</div>';
        html += '<div class="doc-title">' + escapeHTML(file.displayName) + '</div>';
        html += '<div class="doc-info">';
        html += '<span class="info-item"><span class="meta-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> ' + file.modified + '</span>';
        if (file.category) html += '<span class="info-item"><span class="meta-icon"><svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span> ' + escapeHTML(file.category) + '</span>';
        if (file.tags && file.tags.length) {
            html += '<span class="tag-list"><span class="meta-icon"><svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span><span class="tag-text">' + file.tags.slice(0,2).map(t => escapeHTML(t)).join('/') + '</span></span>';
        }
        html += '</div></div>';
        return html;
    }

    function buildBottomCards(file) {
        const shortUrl = getShortUrl(file.name);
        const licenseUrl = file.licenseUrl || '';
        const licenseText = file.license || 'CC BY-NC-SA 4.0';
        const licenseHtml = licenseUrl
            ? `<a href="${escapeHTML(licenseUrl)}" target="_blank" rel="noopener">${escapeHTML(licenseText)}</a>`
            : escapeHTML(licenseText);
        const authorHtml = file.author ? `<span class="info-card-author">${escapeHTML(file.author)}</span>` : '';
        return `
        <div class="article-bottom-cards">
            <div class="share-card">
                <div class="share-card-top">
                    <div class="share-card-icon">
                        <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    </div>
                    <div>
                        <div class="share-card-title">分享</div>
                        <div class="share-card-desc">如果这篇文章对你有帮助，欢迎分享给更多人！</div>
                    </div>
                </div>
                <button class="share-card-btn" id="shareCardBtnInline">分享</button>
            </div>
            <div class="article-info-card">
                <div class="info-card-title">${escapeHTML(file.displayName)}</div>
                <div class="info-card-url">${escapeHTML(shortUrl)}</div>
                <div class="info-card-meta">
                    ${authorHtml ? `<div class="info-card-meta-item">
                        <span class="info-card-meta-label">作者</span>
                        <span>${authorHtml}</span>
                    </div>` : ''}
                    <div class="info-card-meta-item">
                        <span class="info-card-meta-label">发布于</span>
                        <span>${file.modified}</span>
                    </div>
                    <div class="info-card-meta-item">
                        <span class="info-card-meta-label">许可证书</span>
                        <span class="info-card-license">${licenseHtml}</span>
                    </div>
                </div>
            </div>
        </div>`;
    }

    function showNotFound(filename) {
        showReading();
        markdownBody.innerHTML = '<div style="text-align:center;padding:60px 20px;">' +
            '<div style="font-size:4em;font-weight:800;color:var(--accent);line-height:1;margin-bottom:12px;">404</div>' +
            '<div style="font-size:1.2em;font-weight:600;margin-bottom:8px;">文档不存在</div>' +
            '<div style="color:var(--text-secondary);margin-bottom:24px;">' + (filename ? '文件 ' + escapeHTML(filename) + ' 未找到，可能已被删除。' : '你访问的页面不存在。') + '</div>' +
            '<a href="./" style="display:inline-flex;align-items:center;gap:8px;padding:10px 24px;border-radius:10px;background:var(--accent);color:#fff;text-decoration:none;font-weight:600;">返回首页</a>' +
            '</div>';
        document.title = '404 - 文档不存在 | You Markdown';
    }

    async function loadFile(filename, pushState = true) {
        showReading();
        markdownBody.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:40px;">⏳ 加载中...</p>';
        currentFileIndex = allFiles.findIndex(f => f.name === filename);
        updatePrevNext();
        if (pushState) updateUrl(filename);
        try {
            const resp = await fetch(`?action=read&file=${encodeURIComponent(filename)}`);
            const data = await resp.json();
            if (data.success) {
                const fileMeta = allFiles[currentFileIndex] || { displayName: filename.replace(/\.md$/i,''), wordCount: 0, modified: '', tags: [], category: '' };
                document.title = fileMeta.displayName + ' - You Markdown';
                currentFileName = filename;

                let mdContent = data.content;
                mdContent = mdContent.replace(/^(<!--.*?-->)?\s*#\s+.*\r?\n?/, '');
                const parsedHtml = typeof marked !== 'undefined' ? marked.parse(mdContent) : '<pre>' + escapeHTML(mdContent) + '</pre>';

                markdownBody.innerHTML = buildDocHeader(fileMeta) + parsedHtml + buildBottomCards(fileMeta);

                markdownBody.querySelectorAll('table').forEach(table => {
                    if (!table.parentElement.classList.contains('table-wrapper')) {
                        const wrapper = document.createElement('div'); wrapper.className = 'table-wrapper';
                        table.parentNode.insertBefore(wrapper, table); wrapper.appendChild(table);
                    }
                });

                if (typeof hljs !== 'undefined') {
                    markdownBody.querySelectorAll('pre code').forEach(block => hljs.highlightElement(block));
                }

                addCopyButtons();
                extractHeadings();
                markdownBody.querySelectorAll('p').forEach(p => {
                    const links = p.querySelectorAll('a');
                    if (links.length === 1 && p.textContent.trim() === links[0].textContent.trim() && links[0].getAttribute('href') && links[0].getAttribute('href').startsWith('#')) {
                        p.classList.add('toc-link');
                    }
                });
                markdownBody.querySelectorAll('a[href^="#"]').forEach(anchor => {
                    anchor.addEventListener('click', function(e) {
                        e.preventDefault();
                        const href = this.getAttribute('href');
                        const id = decodeURIComponent(href.substring(1));
                        const target = document.getElementById(id);
                        if (target) {
                            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            history.replaceState(null, '', '#' + id);
                        }
                    });
                });
                renderTocPopup();
                renderSidebarToc(currentHeadings);
                bindImageLightbox();
                highlightSidebarItem(filename);
                if (sidebarArticleTitle) sidebarArticleTitle.textContent = fileMeta.displayName;
                if (tocPanel.classList.contains('active') && isReadingView) { renderDocumentOutline(); tocPanelHeader.style.display = 'block'; }
                updateActiveHeading(true);
                updatePrevNext();

                const inlineShareBtn = document.getElementById('shareCardBtnInline');
                if (inlineShareBtn) {
                    inlineShareBtn.addEventListener('click', () => {
                        shareModalOverlay.classList.add('active');
                        shareQrcode.innerHTML = '';
                        new QRCode(shareQrcode, { text: window.location.href, width: 180, height: 180 });
                    });
                }
                updatePrevNext();
            } else {
                showNotFound(filename);
            }
        } catch (err) { showNotFound(filename); }
    }

    window.addEventListener('popstate', () => {
        const fileParam = getUrlParam('file');
        if (fileParam && allFiles.some(f => f.name === fileParam)) loadFile(fileParam, false);
        else showHome(false);
    });

    if (typeof marked !== 'undefined') {
        const renderer = new marked.Renderer();
        renderer.list = (body) => body;
        renderer.listitem = (text) => `<p>${text}</p>`;
        renderer.hr = () => '';
        renderer.heading = function(text, level, raw, slugger) {
            const id = slugger ? slugger.slug(raw) : 'heading-' + text;
            return `<h${level} id="${id}">${text}</h${level}>`;
        };
        marked.setOptions({ gfm: true, breaks: false, smartLists: true, renderer });
    }

    async function init() {
        await loadFileList();
        const fileParam = getUrlParam('file');
        if (fileParam) loadFile(fileParam, false);
        else showHome(false);
    }

    let toastTimer = null;
    function showToast(msg, duration = 2000) {
        toast.textContent = msg;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), duration);
    }

    function openLightbox(src, alt) {
        lightboxImg.src = src;
        lightboxImg.alt = alt || '';
        imgLightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        imgLightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
    imgLightbox.addEventListener('click', (e) => { if (e.target === imgLightbox) closeLightbox(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && imgLightbox.classList.contains('active')) closeLightbox(); });

    function bindImageLightbox() {
        markdownBody.querySelectorAll('img').forEach(img => {
            img.style.cursor = 'zoom-in';
            img.addEventListener('click', () => openLightbox(img.src, img.alt));
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
        if (imgLightbox.classList.contains('active')) return;

        if (isReadingView) {
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (currentFileIndex > 0) loadFile(allFiles[currentFileIndex - 1].name);
            } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                if (currentFileIndex < allFiles.length - 1) loadFile(allFiles[currentFileIndex + 1].name);
            } else if (e.key === 't' || e.key === 'T') {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else if (e.key === 'Escape') {
                e.preventDefault();
                showHome();
            }
        } else {
            if (e.key === 'Escape') {
                closeAllPanels();
            }
        }
    });

    init();
})();

