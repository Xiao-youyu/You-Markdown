

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
    const readingProgressText = document.getElementById('readingProgressText');
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
    const colorResetBtn = document.getElementById('colorResetBtn');
    if (colorResetBtn) colorResetBtn.addEventListener('click', () => { setAccentHue(220); hueSlider.value = 220; });

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
            if (readingProgressText) {
                readingProgressText.textContent = Math.round(pct) + '%';
                readingProgressText.classList.add('active');
            }
        } else {
            topBar.classList.remove('hidden');
            readingProgress.classList.remove('active');
            if (readingProgressText) readingProgressText.classList.remove('active');
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
        if (isReadingView) {
            sessionStorage.setItem('md-read-scroll-' + currentFileName, window.scrollY);
        } else {
            sessionStorage.setItem('md-list-scroll', window.scrollY);
        }
        homeView.style.display = 'block'; readingView.classList.remove('active');
        isReadingView = false; floatingButtons.style.display = 'none'; prevNextNav.style.display = 'none';
        tocPopup.classList.remove('active'); shareModalOverlay.classList.remove('active'); shareQrcode.innerHTML = '';
        showSidebarFileList(); highlightSidebarItem('');
        document.title = 'You Markdown';
        cmtOnArticleHide();
        const savedScroll = sessionStorage.getItem('md-list-scroll');
        if (savedScroll) { requestAnimationFrame(() => window.scrollTo(0, parseInt(savedScroll))); } else { window.scrollTo(0, 0); }
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
                if (!localStorage.getItem('md-keyboard-hint-shown')) {
                    setTimeout(() => showToast('← → 切换文章 | ESC 返回首页 | T 回到顶部', 3000), 500);
                    localStorage.setItem('md-keyboard-hint-shown', '1');
                }
                const savedReadScroll = sessionStorage.getItem('md-read-scroll-' + filename);
                if (savedReadScroll) { requestAnimationFrame(() => window.scrollTo(0, parseInt(savedReadScroll))); }
                
                cmtOnArticleLoad();
            } else {
                showNotFound(filename);
                cmtOnArticleHide();
            }
        } catch (err) { showNotFound(filename); }
    }

    window.addEventListener('popstate', () => {
        const fileParam = getUrlParam('file');
        if (fileParam && allFiles.some(f => f.name === fileParam)) {
            loadFile(fileParam, false);
        } else {
            showHome(false);
        }
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
        if (document.body.dataset.adminLogin) {
            cmtCheckAuth().then(() => {
                if (!cmtUser) {
                    cmtAuthMode = 'login';
                    cmtUpdateAuthUI();
                    cmtOpenModal(cmtAuthModal);
                }
                delete document.body.dataset.adminLogin;
                history.replaceState(null, '', location.pathname);
            });
        }
    }

    let toastTimer = null;
    function showToast(msg, duration = 2000) {
        toast.textContent = msg;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), duration);
    }

    let lightboxImages = [];
    let lightboxIndex = 0;

    function openLightbox(src, alt, index) {
        lightboxImages = Array.from(markdownBody.querySelectorAll('img')).map(i => ({src: i.src, alt: i.alt}));
        lightboxIndex = typeof index === 'number' ? index : lightboxImages.findIndex(i => i.src === src);
        if (lightboxIndex < 0) lightboxIndex = 0;
        lightboxImg.src = src;
        lightboxImg.alt = alt || '';
        imgLightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
        imgLightbox.querySelectorAll('.img-lightbox-close,.img-lightbox-prev,.img-lightbox-next').forEach(b => b.remove());
        const closeBtn = document.createElement('button');
        closeBtn.className = 'img-lightbox-close';
        closeBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        closeBtn.addEventListener('click', closeLightbox);
        imgLightbox.appendChild(closeBtn);
        if (lightboxImages.length > 1) {
            const prevBtn = document.createElement('button');
            prevBtn.className = 'img-lightbox-prev';
            prevBtn.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>';
            prevBtn.addEventListener('click', (e) => { e.stopPropagation(); navigateLightbox(-1); });
            imgLightbox.appendChild(prevBtn);
            const nextBtn = document.createElement('button');
            nextBtn.className = 'img-lightbox-next';
            nextBtn.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>';
            nextBtn.addEventListener('click', (e) => { e.stopPropagation(); navigateLightbox(1); });
            imgLightbox.appendChild(nextBtn);
        }
    }
    function navigateLightbox(dir) {
        lightboxIndex = (lightboxIndex + dir + lightboxImages.length) % lightboxImages.length;
        lightboxImg.src = lightboxImages[lightboxIndex].src;
        lightboxImg.alt = lightboxImages[lightboxIndex].alt || '';
    }
    function closeLightbox() {
        imgLightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
    imgLightbox.addEventListener('click', (e) => { if (e.target === imgLightbox) closeLightbox(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && imgLightbox.classList.contains('active')) closeLightbox(); });

    function bindImageLightbox() {
        markdownBody.querySelectorAll('img').forEach((img, idx) => {
            img.style.cursor = 'zoom-in';
            img.addEventListener('click', () => openLightbox(img.src, img.alt, idx));
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
        if (e.key === 'Escape' && imgLightbox.classList.contains('active')) { closeLightbox(); return; }
        if (e.key === 'ArrowLeft' && imgLightbox.classList.contains('active')) { navigateLightbox(-1); return; }
        if (e.key === 'ArrowRight' && imgLightbox.classList.contains('active')) { navigateLightbox(1); return; }

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

    window.toggleKbdHelp = function toggleKbdHelp() {
        let overlay = document.querySelector('.kbd-help-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'kbd-help-overlay';
            overlay.innerHTML = `
        <div class="kbd-help-box">
            <div class="kbd-help-title">
                <svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><line x1="6" y1="10" x2="6" y2="10"/><line x1="10" y1="10" x2="10" y2="10"/><line x1="14" y1="10" x2="14" y2="10"/><line x1="18" y1="10" x2="18" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/></svg>
                快捷键
            </div>
            <div class="kbd-help-section">
                <h4>导航</h4>
                <div class="kbd-row"><span class="kbd-label">搜索文档</span><span class="kbd-keys"><kbd>/</kbd></span></div>
                <div class="kbd-row"><span class="kbd-label">上一篇文章</span><span class="kbd-keys"><kbd>←</kbd></span></div>
                <div class="kbd-row"><span class="kbd-label">下一篇文章</span><span class="kbd-keys"><kbd>→</kbd></span></div>
                <div class="kbd-row"><span class="kbd-label">返回首页</span><span class="kbd-keys"><kbd>Esc</kbd></span></div>
                <div class="kbd-row"><span class="kbd-label">回到顶部</span><span class="kbd-keys"><kbd>T</kbd></span></div>
            </div>
            <div class="kbd-help-section">
                <h4>阅读</h4>
                <div class="kbd-row"><span class="kbd-label">打印文章</span><span class="kbd-keys"><kbd>P</kbd></span></div>
                <div class="kbd-row"><span class="kbd-label">折叠/展开侧边栏</span><span class="kbd-keys"><kbd>S</kbd></span></div>
                <div class="kbd-row"><span class="kbd-label">关闭弹窗</span><span class="kbd-keys"><kbd>Esc</kbd></span></div>
            </div>
            <div class="kbd-help-section">
                <h4>灯箱</h4>
                <div class="kbd-row"><span class="kbd-label">上一张图片</span><span class="kbd-keys"><kbd>←</kbd></span></div>
                <div class="kbd-row"><span class="kbd-label">下一张图片</span><span class="kbd-keys"><kbd>→</kbd></span></div>
                <div class="kbd-row"><span class="kbd-label">关闭</span><span class="kbd-keys"><kbd>Esc</kbd></span></div>
            </div>
        </div>`;
            overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.remove('show'); });
            document.body.appendChild(overlay);
        }
        overlay.classList.toggle('show');
    }



    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        if (!sb) return;
        const isCollapsed = sb.style.display === 'none';
        sb.style.display = isCollapsed ? 'flex' : 'none';
        document.body.style.paddingLeft = isCollapsed ? '280px' : '0';
        localStorage.setItem('md-sidebar-hidden', isCollapsed ? '0' : '1');
    }
    if (localStorage.getItem('md-sidebar-hidden') === '1') {
        const sb = document.getElementById('sidebar');
        if (sb) { sb.style.display = 'none'; document.body.style.paddingLeft = '0'; }
    }

    (function() {
        const isTyping = () => {
            const el = document.activeElement;
            return el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' || el.isContentEditable);
        };

        document.addEventListener('keydown', (e) => {
            if (isTyping() || imgLightbox.classList.contains('active')) return;

            if ((e.key === '/' || (e.ctrlKey && e.key === 'k')) && !isReadingView) {
                e.preventDefault();
                sidebarSearchInput ? sidebarSearchInput.focus() : searchInput.focus();
                return;
            }

            if (e.key === '?' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                toggleKbdHelp();
                return;
            }

            if (e.key === 's' && !e.ctrlKey && !e.metaKey && !isReadingView) {
                e.preventDefault();
                toggleSidebar();
                return;
            }

            if (e.key === 'p' && !e.ctrlKey && !e.metaKey && isReadingView) {
                e.preventDefault();
                window.print();
                return;
            }
        });
    })();

    const cmtSection = document.getElementById('commentSection');
    const cmtArea = document.getElementById('commentArea');
    const cmtListSection = document.getElementById('commentListSection');
    const cmtCapsuleBar = document.getElementById('cmtCapsuleBar');
    const cmtCapsuleBtn = document.getElementById('cmtCapsuleBtn');
    const cmtUserBar = document.getElementById('cmtUserBar');
    const cmtUserInner = document.getElementById('cmtUserInner');
    const cmtUserAvatar = document.getElementById('cmtUserAvatar');
    const cmtUserGreeting = document.getElementById('cmtUserGreeting');
    const cmtLogoutBtn = document.getElementById('cmtLogoutBtn');
    const cmtTextarea = document.getElementById('cmtTextarea');
    const cmtSendBtn = document.getElementById('cmtSendBtn');
    const cmtList = document.getElementById('cmtList');
    const cmtAuthModal = document.getElementById('cmtAuthModal');
    const cmtAuthTitle = document.getElementById('cmtAuthTitle');
    const cmtAuthSlide = document.getElementById('cmtAuthSlide');
    const cmtLoginForm = document.getElementById('cmtLoginForm');
    const cmtRegForm = document.getElementById('cmtRegForm');
    const cmtLoginQQ = document.getElementById('cmtLoginQQ');
    const cmtLoginPw = document.getElementById('cmtLoginPw');
    const cmtLoginErr = document.getElementById('cmtLoginErr');
    const cmtLoginBtn = document.getElementById('cmtLoginBtn');
    const cmtRegQQ = document.getElementById('cmtRegQQ');
    const cmtRegNick = document.getElementById('cmtRegNick');
    const cmtRegPw = document.getElementById('cmtRegPw');
    const cmtRegErr = document.getElementById('cmtRegErr');
    const cmtRegBtn = document.getElementById('cmtRegBtn');
    const cmtSwitchText = document.getElementById('cmtSwitchText');
    const cmtSwitchBtn = document.getElementById('cmtSwitchBtn');
    const cmtProfileModal = document.getElementById('cmtProfileModal');
    const cmtEditNick = document.getElementById('cmtEditNick');
    const cmtEditSign = document.getElementById('cmtEditSign');
    const cmtProfileErr = document.getElementById('cmtProfileErr');
    const cmtProfileSave = document.getElementById('cmtProfileSave');
    const cmtAdminModal = document.getElementById('cmtAdminModal');
    const cmtAdminQQ = document.getElementById('cmtAdminQQ');
    const cmtAdminNick = document.getElementById('cmtAdminNick');
    const cmtAdminPw = document.getElementById('cmtAdminPw');
    const cmtAdminPw2 = document.getElementById('cmtAdminPw2');
    const cmtAdminErr = document.getElementById('cmtAdminErr');
    const cmtAdminSave = document.getElementById('cmtAdminSave');
    const cmtConfirmOverlay = document.getElementById('cmtConfirmOverlay');
    const cmtConfirmOk = document.getElementById('cmtConfirmOk');
    const cmtConfirmCancel = document.getElementById('cmtConfirmCancel');

    const guestCommentsEnabled = document.body.dataset.guestComments === '1';
    let cmtUser = null;
    let cmtAuthMode = 'login';
    let cmtLongPressTimer = null;
    let cmtAdminFirstLogin = false;
    let cmtConfirmCb = null;
    let cmtExpandedItems = new Set();

    function cmtEscape(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    function cmtGreeting() {
        const h = new Date().getHours();
        if (h < 12) return '上午好'; if (h < 14) return '中午好'; return '下午好';
    }
    function cmtFormatTime(t) {
        if (!t) return '';
        const d = new Date(t.replace(/-/g, '/'));
        const now = Date.now();
        const diff = (now - d.getTime()) / 1000;
        if (diff < 60) return '刚刚';
        if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
        if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
        const Y = d.getFullYear(), M = d.getMonth() + 1, D = d.getDate();
        const hh = d.getHours().toString().padStart(2, '0');
        const mm = d.getMinutes().toString().padStart(2, '0');
        return Y + '-' + M + '-' + D + ' ' + hh + ':' + mm;
    }
    function cmtAvatarHtml(url, name, qq) {
        const initial = cmtEscape((name||'?').charAt(0));
        const src = qq ? 'api.php?action=avatar&qq=' + encodeURIComponent(qq) : (url || '');
        if (src) return '<img src="' + cmtEscape(src) + '" alt="" class="cmt-avatar-img" onerror="this.style.display=\'none\';this.parentNode.querySelector(\'.cmt-avatar-text\').style.display=\'flex\'"/><span class="cmt-avatar-text" style="display:none">' + initial + '</span>';
        return '<span class="cmt-avatar-text">' + initial + '</span>';
    }

    
    function cmtOpenModal(id) { id.classList.add('show'); }
    function cmtCloseModal(id) { id.classList.remove('show'); }
    [cmtAuthModal, cmtProfileModal, cmtAdminModal].forEach(m => {
        if (m) m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
    });
    if (cmtCapsuleBtn) cmtCapsuleBtn.addEventListener('click', () => { cmtAuthMode = 'login'; cmtUpdateAuthUI(); cmtOpenModal(cmtAuthModal); });
    if (cmtUserInner) cmtUserInner.addEventListener('click', () => {
        if (cmtUser) {
            cmtEditNick.value = cmtUser.nickname || '';
            cmtEditSign.value = cmtUser.signature || '';
            cmtOpenModal(cmtProfileModal);
        }
    });
    if (cmtLogoutBtn) cmtLogoutBtn.addEventListener('click', e => {
        e.stopPropagation();
        fetch('api.php?action=logout', { method: 'POST' }).catch(() => {});
        cmtUser = null;
        cmtUpdateUI();
    });
    if (cmtSwitchBtn) cmtSwitchBtn.addEventListener('click', () => {
        cmtAuthSlide.classList.add('slide-out');
        setTimeout(() => {
            cmtAuthMode = cmtAuthMode === 'login' ? 'register' : 'login';
            cmtUpdateAuthUI();
            cmtAuthSlide.classList.remove('slide-out');
            cmtAuthSlide.classList.add('slide-in');
            setTimeout(() => cmtAuthSlide.classList.remove('slide-in'), 250);
        }, 200);
    });
    function cmtUpdateAuthUI() {
        const isLogin = cmtAuthMode === 'login';
        if (cmtAuthTitle) cmtAuthTitle.textContent = isLogin ? '登录' : '注册';
        if (cmtLoginForm) cmtLoginForm.style.display = isLogin ? 'flex' : 'none';
        if (cmtRegForm) cmtRegForm.style.display = isLogin ? 'none' : 'flex';
        if (cmtSwitchText) cmtSwitchText.textContent = isLogin ? '还没有账号？' : '已有账号？';
        if (cmtSwitchBtn) cmtSwitchBtn.textContent = isLogin ? '立即注册' : '去登录';
        if (cmtLoginErr) cmtLoginErr.textContent = '';
        if (cmtRegErr) cmtRegErr.textContent = '';
    }
    if (cmtLoginBtn) cmtLoginBtn.addEventListener('click', () => {
        const qq = cmtLoginQQ.value.trim(), pw = cmtLoginPw.value;
        cmtLoginErr.textContent = '';
        if (!qq || !pw) { cmtLoginErr.textContent = '请填写QQ号和密码'; return; }
        cmtLoginBtn.disabled = true; cmtLoginBtn.textContent = '登录中...';
        fetch('api.php?action=login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ qq, password: pw }) })
            .then(r => r.json()).then(d => {
                if (d.success) { cmtUser = d.user; if (d.isAdminFirstLogin) cmtAdminFirstLogin = true; cmtCloseModal(cmtAuthModal); cmtUpdateUI(); cmtLoad(); }
                else { cmtLoginErr.textContent = d.error || '登录失败'; }
            }).catch(() => { cmtLoginErr.textContent = '网络错误'; })
            .finally(() => { cmtLoginBtn.disabled = false; cmtLoginBtn.textContent = '登录'; });
    });
    if (cmtRegBtn) cmtRegBtn.addEventListener('click', () => {
        const qq = cmtRegQQ.value.trim(), nick = cmtRegNick.value.trim(), pw = cmtRegPw.value;
        cmtRegErr.textContent = '';
        if (!qq || !pw) { cmtRegErr.textContent = '请填写QQ号和密码'; return; }
        if (pw.length < 6) { cmtRegErr.textContent = '密码至少6位'; return; }
        cmtRegBtn.disabled = true; cmtRegBtn.textContent = '注册中...';
        fetch('api.php?action=register', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ qq, nickname: nick, password: pw }) })
            .then(r => r.json()).then(d => {
                if (d.success) { cmtUser = d.user; cmtCloseModal(cmtAuthModal); cmtUpdateUI(); cmtLoad(); }
                else { cmtRegErr.textContent = d.error || '注册失败'; }
            }).catch(() => { cmtRegErr.textContent = '网络错误'; })
            .finally(() => { cmtRegBtn.disabled = false; cmtRegBtn.textContent = '注册'; });
    });
    if (cmtProfileSave) cmtProfileSave.addEventListener('click', () => {
        const nick = cmtEditNick.value.trim(), sign = cmtEditSign.value.trim();
        cmtProfileErr.textContent = '';
        if (!nick) { cmtProfileErr.textContent = '昵称不能为空'; return; }
        fetch('api.php?action=update_profile', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ nickname: nick, signature: sign }) })
            .then(r => r.json()).then(d => {
                if (d.success) { cmtUser.nickname = nick; cmtUser.signature = sign; cmtCloseModal(cmtProfileModal); cmtUpdateUI(); }
                else { cmtProfileErr.textContent = d.error || '保存失败'; }
            }).catch(() => { cmtProfileErr.textContent = '网络错误'; });
    });
    if (cmtAdminSave) cmtAdminSave.addEventListener('click', () => {
        const qq = cmtAdminQQ.value.trim(), nick = cmtAdminNick.value.trim(), pw = cmtAdminPw.value, pw2 = cmtAdminPw2.value;
        cmtAdminErr.textContent = '';
        if (!qq) { cmtAdminErr.textContent = '请填写QQ号'; return; }
        if (!nick) { cmtAdminErr.textContent = '请填写昵称'; return; }
        if (pw && pw.length < 6) { cmtAdminErr.textContent = '密码至少6位'; return; }
        if (pw !== pw2) { cmtAdminErr.textContent = '两次密码不一致'; return; }
        fetch('api.php?action=admin_setup', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ qq, nickname: nick, password: pw }) })
            .then(r => r.json()).then(d => {
                if (d.success) { if (cmtUser) { cmtUser.nickname = nick; cmtUser.qq = qq; } cmtCloseModal(cmtAdminModal); cmtUpdateUI(); }
                else { cmtAdminErr.textContent = d.error || '保存失败'; }
            }).catch(() => { cmtAdminErr.textContent = '网络错误'; });
    });

    function cmtUpdateUI() {
        const loggedIn = !!cmtUser;
        const canComment = loggedIn || guestCommentsEnabled;
        if (cmtCapsuleBar) cmtCapsuleBar.style.display = loggedIn ? 'none' : 'block';
        if (cmtUserBar) cmtUserBar.style.display = loggedIn ? 'block' : 'none';
        if (cmtTextarea) cmtTextarea.disabled = !canComment;
        if (loggedIn) {
            const name = cmtUser.nickname || '用户';
            const avatarUrl = cmtUser.avatar || '';
            if (cmtUserAvatar) {
                const avatarSrc = cmtUser.qq ? 'api.php?action=avatar&qq=' + encodeURIComponent(cmtUser.qq) : (cmtUser.avatar || '');
                if (avatarSrc) {
                    cmtUserAvatar.innerHTML = '<img src="' + cmtEscape(avatarSrc) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%" onerror="this.style.display=\'none\'">';
                } else {
                    cmtUserAvatar.textContent = name.charAt(0);
                }
            }
            if (cmtUserGreeting) cmtUserGreeting.textContent = cmtGreeting() + '，' + name;
        } else {
            if (cmtUserAvatar) cmtUserAvatar.textContent = '';
        }
        
        if (loggedIn && cmtUser.role === 'admin' && cmtAdminFirstLogin) {
            cmtAdminFirstLogin = false;
            setTimeout(() => cmtOpenModal(cmtAdminModal), 300);
        }
    }

    
    if (cmtTextarea) cmtTextarea.addEventListener('input', () => {
        if (cmtSendBtn) cmtSendBtn.classList.toggle('has-content', cmtTextarea.value.trim().length > 0);
    });
    if (cmtSendBtn) cmtSendBtn.addEventListener('click', () => {
        const content = cmtTextarea.value.trim();
        if (!content) return;
        if (!cmtUser && !guestCommentsEnabled) return;
        cmtSendBtn.disabled = true;
        fetch('api.php?action=post', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ article: currentFileName, content }) })
            .then(r => r.json()).then(d => {
                if (d.success) { cmtTextarea.value = ''; cmtSendBtn.classList.remove('has-content'); cmtLoad(); }
                else showToast(d.error || '评论失败');
            }).catch(() => showToast('网络错误')).finally(() => { cmtSendBtn.disabled = false; });
    });

    
    function cmtLoad() {
        if (!currentFileName) return;
        
        cmtExpandedItems.clear();
        cmtList.querySelectorAll('.cmt-item').forEach(item => {
            const expandBtn = item.querySelector('.cmt-reply-expand');
            const replyList = item.querySelector('.cmt-reply-list');
            if (expandBtn && expandBtn.classList.contains('open')) {
                cmtExpandedItems.add(item.dataset.id);
            }
            if (replyList && replyList.classList.contains('show')) {
                cmtExpandedItems.add(item.dataset.id);
            }
        });
        fetch('api.php?action=get&article=' + encodeURIComponent(currentFileName))
            .then(r => r.json()).then(d => { if (d.success) { cmtRender(d.comments); cmtRestoreExpandState(); } })
            .catch(() => {});
    }
    function cmtRestoreExpandState() {
        cmtExpandedItems.forEach(id => {
            const item = cmtList.querySelector('.cmt-item[data-id="' + id + '"]');
            if (item) {
                const expandBtn = item.querySelector('.cmt-reply-expand');
                const replyList = item.querySelector('.cmt-reply-list');
                if (expandBtn) expandBtn.classList.add('open');
                if (replyList) replyList.classList.add('show');
            }
        });
    }

    function cmtRenderReply(r, level, parentNick) {
        if (level > 10) return '<div class="cmt-reply-item"><div class="cmt-reply-text" style="color:var(--text-muted)">[嵌套过深]</div></div>';
        const isAdmin = cmtUser && cmtUser.role === 'admin';
        const rDel = isAdmin || (cmtUser && cmtUser.id === r.user_id);
        let nestedHtml = '';
        if (r.replies && r.replies.length) {
            nestedHtml = '<div class="cmt-reply-nested">' + r.replies.map(nr => cmtRenderReply(nr, level + 1, r.nickname || '')).join('') + '</div>';
        }
        const replyToHtml = (level >= 2 && parentNick) ? '<span class="cmt-reply-arrow">▶︎</span><span class="cmt-reply-to">' + cmtEscape(parentNick) + '</span>' : '';
        return '<div class="cmt-reply-item" data-id="' + r.id + '" data-del="' + rDel + '">' +
            '<div class="cmt-reply-top"><div class="cmt-reply-avatar">' + cmtAvatarHtml(r.avatar, r.nickname, r.qq) + '</div>' +
            '<div class="cmt-reply-info"><div class="cmt-reply-name-row"><span class="cmt-reply-name">' + cmtEscape(r.nickname||'') + '</span>' + replyToHtml + '<span class="cmt-reply-time">' + cmtFormatTime(r.created_at) + '</span></div>' +
            '</div></div>' +
            '<div class="cmt-reply-text">' + cmtEscape(r.content) + '</div>' +
            '<div class="cmt-reply-actions">' +
            '<button class="cmt-reply-act cmt-like-btn"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span>' + (r.likes||0) + '</span></button>' +
            '<button class="cmt-reply-act cmt-reply-btn"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>回复</button>' +
            (rDel ? '<button class="cmt-reply-act cmt-del-btn" data-id="' + r.id + '"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>删除</button>' : '') +
            '</div>' + nestedHtml + '</div>';
    }

    function cmtRender(comments) {
        if (!comments || !comments.length) {
            cmtList.innerHTML = '<div class="cmt-empty"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>暂无评论，快来抢沙发吧</div>';
            return;
        }
        const isAdmin = cmtUser && cmtUser.role === 'admin';
        cmtList.innerHTML = comments.map(c => {
            const isOwner = cmtUser && cmtUser.id === c.user_id;
            const canDel = isAdmin || isOwner;
            const sign = c.signature ? '<div class="cmt-sign">' + cmtEscape(c.signature) + '</div>' : '';
            let repliesHtml = '';
            if (c.replies && c.replies.length) {
                repliesHtml = '<div class="cmt-reply-list">' + c.replies.map(r => cmtRenderReply(r, 1, c.nickname || '')).join('') + '</div>';
            }
            const totalReplies = (function countReplies(arr) { return arr.reduce((n, r) => n + 1 + countReplies(r.replies || []), 0); })(c.replies || []);
            const expandBtn = totalReplies ? '<button class="cmt-reply-expand"><span>' + totalReplies + '条回复</span><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></button>' : '';
            const noSignCls = !c.signature ? ' cmt-no-sign' : '';
            return '<div class="cmt-item" data-id="' + c.id + '" data-del="' + canDel + '">' +
                '<div class="cmt-top' + noSignCls + '"><div class="cmt-avatar">' + cmtAvatarHtml(c.avatar, c.nickname, c.qq) + '</div>' +
                '<div class="cmt-info"><div class="cmt-name-row"><span class="cmt-name">' + cmtEscape(c.nickname||'') + '</span><span class="cmt-time">' + cmtFormatTime(c.created_at) + '</span></div>' +
                sign + '</div></div>' +
                '<div class="cmt-text">' + cmtEscape(c.content) + '</div>' +
                '<div class="cmt-actions">' +
                '<button class="cmt-act cmt-like-btn"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span>' + (c.likes||0) + '</span></button>' +
                '<button class="cmt-act cmt-reply-btn"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>回复</button>' +
                (canDel ? '<button class="cmt-act cmt-del-btn" data-id="' + c.id + '"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>删除</button>' : '') +
                expandBtn + '</div>' + repliesHtml +
                '<div class="cmt-reply-input-wrap"><textarea placeholder="写下你的回复..."></textarea><div class="cmt-reply-input-bottom"><button class="cmt-reply-send-btn">发送</button></div></div></div>';
        }).join('');
        cmtBindEvents();
    }

    function cmtSendReply(wrap, target) {

        const ta = wrap.querySelector('textarea');
        const content = ta.value.trim();
        if (!content || !cmtUser) return;
        const parentId = target.dataset.id;
        
        let ancestor = target.parentElement;
        while (ancestor && ancestor !== cmtList) {
            if (ancestor.classList && ancestor.classList.contains('cmt-item')) {
                cmtExpandedItems.add(ancestor.dataset.id);
            }
            ancestor = ancestor.parentElement;
        }
        fetch('api.php?action=reply', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ article: currentFileName, parent_id: parentId, content }) })
            .then(r => r.json()).then(d => { if (d.success) { ta.value = ''; wrap.classList.remove('show'); cmtLoad(); } else showToast(d.error || '回复失败'); })
            .catch(() => showToast('网络错误'));
    }
    function cmtBindEvents() {
        
        cmtList.querySelectorAll('.cmt-like-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.classList.toggle('liked');
                const span = btn.querySelector('span');
                if (span) { let n = parseInt(span.textContent); span.textContent = btn.classList.contains('liked') ? n + 1 : Math.max(0, n - 1); }
            });
        });
        
        cmtList.querySelectorAll('.cmt-reply-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!cmtUser && !guestCommentsEnabled) { cmtCapsuleBtn && cmtCapsuleBtn.click(); return; }
                const target = btn.closest('.cmt-item, .cmt-reply-item');
                let wrap = target.querySelector(':scope > .cmt-reply-input-wrap');
                if (!wrap) {
                    wrap = document.createElement('div');
                    wrap.className = 'cmt-reply-input-wrap';
                    wrap.innerHTML = '<textarea placeholder="写下你的回复..."></textarea><div class="cmt-reply-input-bottom"><button class="cmt-reply-send-btn">发送</button></div>';
                    target.appendChild(wrap);
                    wrap.querySelector('textarea').addEventListener('input', function() {
                        const sbtn = wrap.querySelector('.cmt-reply-send-btn');
                        if (sbtn) sbtn.classList.toggle('has-content', this.value.trim().length > 0);
                    });
                    wrap.querySelector('.cmt-reply-send-btn').addEventListener('click', function() { cmtSendReply(wrap, target); });
                }
                wrap.classList.toggle('show');
                if (wrap.classList.contains('show')) wrap.querySelector('textarea').focus();
            });
        });
        
        cmtList.querySelectorAll('.cmt-reply-send-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const wrap = btn.closest('.cmt-reply-input-wrap');
                const target = btn.closest('.cmt-item, .cmt-reply-item');
                cmtSendReply(wrap, target);
            });
        });
        
        cmtList.querySelectorAll('.cmt-reply-input-wrap textarea').forEach(ta => {
            ta.addEventListener('input', () => {
                const btn = ta.closest('.cmt-reply-input-wrap').querySelector('.cmt-reply-send-btn');
                if (btn) btn.classList.toggle('has-content', ta.value.trim().length > 0);
            });
        });
        
        cmtList.querySelectorAll('.cmt-del-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.dataset.id;
                if (id) {
                    cmtConfirmCb = () => {
                        fetch('api.php?action=delete', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, article: currentFileName }) })
                            .then(r => r.json()).then(d => { if (d.success) cmtLoad(); else showToast(d.error || '删除失败'); })
                            .catch(() => showToast('网络错误'));
                    };
                    cmtConfirmOverlay.classList.add('show');
                }
            });
        });
        
        cmtList.querySelectorAll('.cmt-reply-expand').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.classList.toggle('open');
                const list = btn.closest('.cmt-item').querySelector('.cmt-reply-list');
                if (list) list.classList.toggle('show');
            });
        });
        
        cmtList.querySelectorAll('.cmt-reply-item[data-del="true"]').forEach(el => {
            let timer;
            const start = (e) => { e.stopPropagation(); timer = setTimeout(() => cmtShowConfirm(el), 600); };
            const clear = () => clearTimeout(timer);
            el.addEventListener('pointerdown', start);
            el.addEventListener('pointerup', clear);
            el.addEventListener('pointercancel', clear);
            el.addEventListener('pointermove', clear);
        });
        cmtList.querySelectorAll('.cmt-item[data-del="true"]').forEach(el => {
            let timer;
            const start = () => { timer = setTimeout(() => cmtShowConfirm(el), 600); };
            const clear = () => clearTimeout(timer);
            el.addEventListener('pointerdown', start);
            el.addEventListener('pointerup', clear);
            el.addEventListener('pointercancel', clear);
            el.addEventListener('pointermove', clear);
            const textEl = el.querySelector('.cmt-text');
            if (textEl) {
                textEl.style.cursor = 'pointer';
                textEl.addEventListener('pointerdown', (e) => { e.stopPropagation(); timer = setTimeout(() => cmtShowConfirm(el), 600); });
                textEl.addEventListener('pointerup', clear);
                textEl.addEventListener('pointercancel', clear);
            }
        });
    }

    function cmtShowConfirm(el) {
        cmtConfirmCb = () => {
            const id = el.dataset.id;
            fetch('api.php?action=delete', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, article: currentFileName }) })
                .then(r => r.json()).then(d => { if (d.success) cmtLoad(); else showToast(d.error || '删除失败'); })
                .catch(() => showToast('网络错误'));
        };
        cmtConfirmOverlay.classList.add('show');
    }
    if (cmtConfirmOk) cmtConfirmOk.addEventListener('click', () => { if (cmtConfirmCb) cmtConfirmCb(); cmtConfirmOverlay.classList.remove('show'); cmtConfirmCb = null; });
    if (cmtConfirmCancel) cmtConfirmCancel.addEventListener('click', () => { cmtConfirmOverlay.classList.remove('show'); cmtConfirmCb = null; });

    
    function cmtCheckAuth() {
        return fetch('api.php?action=check').then(r => r.json()).then(d => {
            if (d.success && d.loggedIn) {
                cmtUser = d.user;
                if (d.isAdminFirstLogin) cmtAdminFirstLogin = true;
            }
            cmtUpdateUI();
        }).catch(() => cmtUpdateUI());
    }

    
    const _cmtOrigLoad = typeof loadFile === 'function' ? loadFile : null;
    const _cmtOrigHome = typeof showHome === 'function' ? showHome : null;

    
    function cmtOnArticleLoad() {
        if (cmtSection) cmtSection.style.display = 'block';
        if (cmtArea) cmtArea.style.display = 'block';
        if (cmtListSection) cmtListSection.style.display = 'block';
        cmtCheckAuth().then(() => {
            cmtLoad();
            if (document.body.dataset.adminLogin && !cmtUser) {
                cmtAuthMode = 'login';
                cmtUpdateAuthUI();
                cmtOpenModal(cmtAuthModal);
                delete document.body.dataset.adminLogin;
                history.replaceState(null, '', location.pathname);
            }
        });
    }
    function cmtOnArticleHide() {
        if (cmtSection) cmtSection.style.display = 'none';
        if (cmtArea) cmtArea.style.display = 'none';
        if (cmtListSection) cmtListSection.style.display = 'none';
    }

    init();
})();

