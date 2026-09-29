
const StorageService = {
    PREFIX: 'studenthub_',

    save(key, data) {
        try {
            const payload = {
                timestamp: new Date().toISOString(),
                data: data
            };
            localStorage.setItem(this.PREFIX + key, JSON.stringify(payload));
        } catch (err) {
            console.warn('Storage quota exceeded or disabled:', err);
        }
    },

    load(key) {
        try {
            const raw = localStorage.getItem(this.PREFIX + key);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (err) {
            console.error('Error parsing cached data:', err);
            return null;
        }
    },

    clear(key) {
        localStorage.removeItem(this.PREFIX + key);
    }
};

const DataService = {
    async fetchData(endpoint, cacheKey) {
        // Try fetching fresh data via Fetch API
        try {
            const response = await fetch(endpoint, { cache: 'no-cache' });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            const data = await response.json();
            // Update offline cache
            StorageService.save(cacheKey, data);
            return {
                data: data,
                fromCache: false,
                timestamp: new Date().toISOString()
            };
        } catch (fetchErr) {
            console.warn(`Fetch error for ${endpoint}, attempting to read from cache...`, fetchErr);
            const cached = StorageService.load(cacheKey);
            if (cached && cached.data) {
                return {
                    data: cached.data,
                    fromCache: true,
                    timestamp: cached.timestamp
                };
            }
            // If individual file fetch failed and no cache, try reading from unified api.json
            try {
                const apiRes = await fetch('api.json');
                if (apiRes.ok) {
                    const apiData = await apiRes.json();
                    let fallbackData = null;
                    if (cacheKey === 'events') fallbackData = apiData.events;
                    else if (cacheKey === 'students') fallbackData = apiData.students;
                    else if (cacheKey === 'faqs') fallbackData = apiData.faqs;
                    else if (cacheKey === 'geo') fallbackData = apiData.geoLocations;

                    if (fallbackData) {
                        StorageService.save(cacheKey, fallbackData);
                        return {
                            data: fallbackData,
                            fromCache: false,
                            timestamp: new Date().toISOString()
                        };
                    }
                }
            } catch (apiErr) {
                console.warn('Fallback api.json also failed:', apiErr);
            }
            throw new Error(`Could not load data from ${endpoint} and no offline cache was found.`);
        }
    }
};

// ==========================================
// 2. Application State
// ==========================================
const AppState = {
    currentTab: 'events', // 'events' | 'students' | 'faqs' | 'locations'
    rawDatasets: {
        events: [],
        students: [],
        faqs: [],
        geo: []
    },
    filterOptions: {
        searchQuery: '',
        category: 'ALL',
        status: 'ALL',
        sortBy: 'default',
        sortOrder: 'asc',
        currentPage: 1,
        pageSize: 6
    },
    isOffline: !navigator.onLine,
    isUsingCache: false,
    cacheTimestamp: null,
    isLoading: false
};

// ==========================================
// 3. Array Operations: Search, Filter, Sort, Pagination
// ==========================================
const ArrayOps = {
    search(list, query, tab) {
        if (!query || !query.trim()) return list;
        const q = query.trim().toLowerCase();

        return list.filter(item => {
            if (tab === 'events') {
                return (
                    item.title?.toLowerCase().includes(q) ||
                    item.category?.toLowerCase().includes(q) ||
                    item.venue?.toLowerCase().includes(q) ||
                    item.coordinator?.toLowerCase().includes(q) ||
                    item.description?.toLowerCase().includes(q) ||
                    (item.tags && item.tags.some(t => t.toLowerCase().includes(q)))
                );
            } else if (tab === 'students') {
                return (
                    item.name?.toLowerCase().includes(q) ||
                    item.enrollment?.toLowerCase().includes(q) ||
                    item.branch?.toLowerCase().includes(q) ||
                    item.city?.toLowerCase().includes(q) ||
                    item.role?.toLowerCase().includes(q) ||
                    (item.skills && item.skills.some(s => s.toLowerCase().includes(q)))
                );
            } else if (tab === 'faqs') {
                return (
                    item.question?.toLowerCase().includes(q) ||
                    item.answer?.toLowerCase().includes(q) ||
                    item.category?.toLowerCase().includes(q) ||
                    (item.tags && item.tags.some(t => t.toLowerCase().includes(q)))
                );
            }
            return false;
        });
    },

    filter(list, category, status, tab) {
        return list.filter(item => {
            let catMatch = true;
            let statusMatch = true;

            if (category && category !== 'ALL') {
                if (tab === 'events') {
                    catMatch = item.category?.toLowerCase() === category.toLowerCase();
                } else if (tab === 'students') {
                    catMatch = item.branch?.toLowerCase() === category.toLowerCase();
                } else if (tab === 'faqs') {
                    catMatch = item.category?.toLowerCase() === category.toLowerCase();
                }
            }

            if (status && status !== 'ALL') {
                if (tab === 'events') {
                    statusMatch = item.status?.toLowerCase() === status.toLowerCase();
                } else if (tab === 'students') {
                    statusMatch = item.status?.toLowerCase() === status.toLowerCase();
                } else if (tab === 'faqs') {
                    if (status === 'IMPORTANT') statusMatch = Boolean(item.isImportant);
                    else if (status === 'GENERAL') statusMatch = !item.isImportant;
                }
            }

            return catMatch && statusMatch;
        });
    },

    sort(list, sortBy, sortOrder, tab) {
        if (!sortBy || sortBy === 'default') return list;
        const sorted = [...list];
        const dir = sortOrder === 'desc' ? -1 : 1;

        return sorted.sort((a, b) => {
            if (tab === 'events') {
                if (sortBy === 'title') {
                    return dir * a.title.localeCompare(b.title);
                } else if (sortBy === 'date') {
                    return dir * (new Date(a.date) - new Date(b.date));
                } else if (sortBy === 'rating') {
                    return dir * ((a.rating || 0) - (b.rating || 0));
                } else if (sortBy === 'seats') {
                    return dir * ((a.registeredCount || 0) - (b.registeredCount || 0));
                }
            } else if (tab === 'students') {
                if (sortBy === 'name') {
                    return dir * a.name.localeCompare(b.name);
                } else if (sortBy === 'gpa') {
                    return dir * (a.gpa - b.gpa);
                } else if (sortBy === 'semester') {
                    return dir * (a.semester - b.semester);
                }
            } else if (tab === 'faqs') {
                if (sortBy === 'views') {
                    return dir * ((a.views || 0) - (b.views || 0));
                } else if (sortBy === 'question') {
                    return dir * a.question.localeCompare(b.question);
                }
            }
            return 0;
        });
    },

    paginate(list, page, pageSize) {
        if (pageSize === 'ALL' || pageSize >= list.length) {
            return {
                items: list,
                totalPages: 1,
                currentPage: 1,
                totalItems: list.length,
                startIndex: 1,
                endIndex: list.length
            };
        }

        const size = parseInt(pageSize, 10) || 6;
        const totalPages = Math.max(1, Math.ceil(list.length / size));
        const validPage = Math.min(Math.max(1, page), totalPages);
        const startIndex = (validPage - 1) * size;
        const endIndex = Math.min(startIndex + size, list.length);
        const items = list.slice(startIndex, endIndex);

        return {
            items,
            totalPages,
            currentPage: validPage,
            totalItems: list.length,
            startIndex: list.length === 0 ? 0 : startIndex + 1,
            endIndex: endIndex
        };
    }
};

// ==========================================
// 4. UI Templates & Renderers
// ==========================================
const UI = {
    elements: {
        container: document.querySelector('#dataContentArea'),
        metricsCount: document.querySelector('#metricsCount'),
        paginationArea: document.querySelector('#paginationArea'),
        statusBadge: document.querySelector('#networkStatusBadge'),
        cacheBadge: document.querySelector('#cacheStatusBadge'),
        searchInput: document.querySelector('#searchInput'),
        clearSearchBtn: document.querySelector('#clearSearchBtn'),
        categorySelect: document.querySelector('#categoryFilter'),
        statusSelect: document.querySelector('#statusFilter'),
        sortSelect: document.querySelector('#sortBySelect'),
        sortOrderBtn: document.querySelector('#sortOrderBtn'),
        pageSizeSelect: document.querySelector('#pageSizeSelect'),
        resetFiltersBtn: document.querySelector('#resetFiltersBtn'),
        syncBtn: document.querySelector('#syncCacheBtn'),
        tabButtons: document.querySelectorAll('.p6-tab-btn'),
        countrySelect: document.querySelector('#countrySelect'),
        stateSelect: document.querySelector('#stateSelect'),
        citySelect: document.querySelector('#citySelect'),
        locationStats: document.querySelector('#locationResults')
    },

    showToast(message) {
        let toast = document.querySelector('#p6Toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'p6Toast';
            toast.className = 'p6-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    },

    renderSkeletons(count = 6) {
        if (!this.elements.container) return;
        const cards = Array.from({ length: count }, () => `
            <div class="skeleton-card" aria-hidden="true">
                <div class="skeleton-line short"></div>
                <div class="skeleton-line tall"></div>
                <div class="skeleton-line medium"></div>
                <div class="skeleton-line short"></div>
                <div class="skeleton-line medium" style="margin-top:20px;"></div>
            </div>
        `).join('');
        this.elements.container.innerHTML = `<div class="data-grid">${cards}</div>`;
        if (this.elements.paginationArea) this.elements.paginationArea.innerHTML = '';
        if (this.elements.metricsCount) this.elements.metricsCount.innerHTML = 'Loading records...';
    },

    renderError(message, retryCallback) {
        if (!this.elements.container) return;
        this.elements.container.innerHTML = `
            <div class="state-box">
                <div class="state-box-icon">⚠️</div>
                <h3>Failed to Load Data</h3>
                <p>${message}</p>
                <button id="p6RetryBtn" class="btn" type="button" style="margin-top:10px;">Retry Loading</button>
            </div>
        `;
        document.querySelector('#p6RetryBtn')?.addEventListener('click', retryCallback);
        if (this.elements.paginationArea) this.elements.paginationArea.innerHTML = '';
        if (this.elements.metricsCount) this.elements.metricsCount.innerHTML = 'Error';
    },

    renderEmpty() {
        if (!this.elements.container) return;
        this.elements.container.innerHTML = `
            <div class="state-box">
                <div class="state-box-icon">🔍</div>
                <h3>No Matching Records Found</h3>
                <p>Try clearing your search query or adjusting active filters to discover records.</p>
                <button id="p6EmptyResetBtn" class="btn" type="button">Reset Filters</button>
            </div>
        `;
        document.querySelector('#p6EmptyResetBtn')?.addEventListener('click', () => {
            App.resetFilters();
        });
        if (this.elements.paginationArea) this.elements.paginationArea.innerHTML = '';
    },

    renderEventCards(events) {
        return `
            <div class="data-grid">
                ${events.map(evt => {
                    const statusClass = evt.status === 'Registration Open' ? 'status-open' 
                                      : evt.status === 'Almost Full' ? 'status-almost' 
                                      : 'status-closed';
                    const fillPercent = Math.min(100, Math.round(((evt.registeredCount || 0) / (evt.maxCapacity || 1)) * 100));

                    return `
                        <article class="card" id="event-${evt.id}">
                            <div>
                                <div class="card-top">
                                    <span class="card-category">${evt.category || 'General'}</span>
                                    <span class="card-status ${statusClass}">${evt.status}</span>
                                </div>
                                <h3 class="card-title">${evt.title}</h3>
                                <p class="card-desc">${evt.description}</p>
                                
                                <div class="card-meta-list">
                                    <div class="card-meta-item">
                                        <span>📅 <b>Date:</b> ${evt.date} (${evt.durationDays} ${evt.durationDays === 1 ? 'day' : 'days'})</span>
                                    </div>
                                    <div class="card-meta-item">
                                        <span>📍 <b>Venue:</b> ${evt.venue}</span>
                                    </div>
                                    <div class="card-meta-item">
                                        <span>👤 <b>Lead:</b> ${evt.coordinator}</span>
                                    </div>
                                    <div class="card-meta-item" style="margin-top:4px;">
                                        <div style="display:flex; justify-content:space-between; width:100%; font-size:11px;">
                                            <span>Registered: <b>${evt.registeredCount}/${evt.maxCapacity}</b></span>
                                            <span><b>${fillPercent}%</b></span>
                                        </div>
                                        <div style="width:100%; height:6px; background:var(--input); border-radius:3px; overflow:hidden; border:1px solid var(--border); margin-top:2px;">
                                            <div style="width:${fillPercent}%; height:100%; background:var(--accent); border-radius:3px;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-tags">
                                    ${(evt.tags || []).map(t => `<span class="tag-pill">#${t}</span>`).join('')}
                                </div>
                            </div>

                            <div class="card-footer">
                                <span class="card-rating">★ ${evt.rating || '4.5'}</span>
                                <span style="font-size:12px; font-weight:600; color:var(--text);">Fee: ${typeof evt.registrationFee === 'number' ? '₹' + evt.registrationFee : evt.registrationFee}</span>
                                <a href="../practical 2/Event.html" class="btn" style="padding:6px 12px; font-size:12px;">Register</a>
                            </div>
                        </article>
                    `;
                }).join('')}
            </div>
        `;
    },

    renderStudentCards(students) {
        return `
            <div class="data-grid">
                ${students.map(stu => `
                    <article class="card student-card" id="student-${stu.id}">
                        <div>
                            <div class="student-header">
                                <img src="${stu.avatar}" alt="${stu.name}" class="student-avatar" loading="lazy">
                                <div class="student-info">
                                    <h3>${stu.name}</h3>
                                    <div class="student-sub">${stu.enrollment} • Sem ${stu.semester}</div>
                                    <span class="student-gpa-badge">GPA: ${stu.gpa}</span>
                                </div>
                            </div>

                            <div class="card-meta-list">
                                <div class="card-meta-item">
                                    <span>🎓 <b>Branch:</b> ${stu.branch}</span>
                                </div>
                                <div class="card-meta-item">
                                    <span>💼 <b>Role:</b> ${stu.role || 'Student'}</span>
                                </div>
                                <div class="card-meta-item">
                                    <span>📍 <b>Location:</b> ${stu.city}, ${stu.state}, ${stu.country}</span>
                                </div>
                                <div class="card-meta-item">
                                    <span>📊 <b>Attendance:</b> ${stu.attendance}</span>
                                </div>
                                <div class="card-meta-item">
                                    <span>✉️ <b>Email:</b> ${stu.email}</span>
                                </div>
                            </div>

                            <div class="card-tags" style="margin-top:10px;">
                                ${(stu.skills || []).map(s => `<span class="tag-pill">${s}</span>`).join('')}
                            </div>
                        </div>

                        <div class="card-footer">
                            <span class="card-category" style="font-size:10px;">${stu.badge || stu.status}</span>
                            <a href="../practical 2/profile.html" class="btn" style="padding:6px 12px; font-size:12px;">View Profile</a>
                        </div>
                    </article>
                `).join('')}
            </div>
        `;
    },

    renderFaqList(faqs) {
        return `
            <div class="faq-list">
                ${faqs.map((faq, idx) => `
                    <div class="faq-item ${idx === 0 ? 'expanded' : ''}" id="faq-${faq.id}">
                        <button class="faq-item-header" type="button" aria-expanded="${idx === 0 ? 'true' : 'false'}">
                            <span>
                                ${faq.isImportant ? `<span class="faq-badge-important">Important</span>` : ''}
                                ${faq.question}
                            </span>
                            <span class="faq-chevron">▼</span>
                        </button>
                        <div class="faq-item-body">
                            <p style="margin-bottom:12px;">${faq.answer}</p>
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; font-size:12px; color:var(--muted); border-top:1px dashed var(--border); padding-top:8px;">
                                <div>
                                    ${(faq.tags || []).map(t => `<span class="tag-pill" style="margin-right:4px;">#${t}</span>`).join('')}
                                </div>
                                <span>Category: <b>${faq.category}</b> • Views: <b>${faq.views || 0}</b></span>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    },

    renderPaginationControls(paginationData) {
        const { totalPages, currentPage } = paginationData;
        if (totalPages <= 1) {
            this.elements.paginationArea.innerHTML = '';
            return;
        }

        let buttonsHtml = `
            <button class="page-btn" id="p6FirstPage" ${currentPage === 1 ? 'disabled' : ''} title="First Page">«</button>
            <button class="page-btn" id="p6PrevPage" ${currentPage === 1 ? 'disabled' : ''} title="Previous Page">‹</button>
        `;

        // Render page numbers (smart window)
        const maxPagesToShow = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
        let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

        if (endPage - startPage + 1 < maxPagesToShow) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        for (let p = startPage; p <= endPage; p++) {
            buttonsHtml += `
                <button class="page-btn ${p === currentPage ? 'active' : ''}" data-page="${p}">${p}</button>
            `;
        }

        buttonsHtml += `
            <button class="page-btn" id="p6NextPage" ${currentPage === totalPages ? 'disabled' : ''} title="Next Page">›</button>
            <button class="page-btn" id="p6LastPage" ${currentPage === totalPages ? 'disabled' : ''} title="Last Page">»</button>
        `;

        this.elements.paginationArea.innerHTML = buttonsHtml;

        // Attach event listeners
        this.elements.paginationArea.querySelector('#p6FirstPage')?.addEventListener('click', () => App.goToPage(1));
        this.elements.paginationArea.querySelector('#p6PrevPage')?.addEventListener('click', () => App.goToPage(currentPage - 1));
        this.elements.paginationArea.querySelector('#p6NextPage')?.addEventListener('click', () => App.goToPage(currentPage + 1));
        this.elements.paginationArea.querySelector('#p6LastPage')?.addEventListener('click', () => App.goToPage(totalPages));

        this.elements.paginationArea.querySelectorAll('.page-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = parseInt(btn.getAttribute('data-page'), 10);
                App.goToPage(target);
            });
        });
    },

    updateFilterDropdowns(tab, currentDataset) {
        if (!this.elements.categorySelect || !this.elements.statusSelect) return;

        // Populate Categories dynamically based on data
        let categories = [];
        let statuses = [];

        if (tab === 'events') {
            categories = Array.from(new Set(currentDataset.map(item => item.category).filter(Boolean)));
            statuses = ['Registration Open', 'Almost Full', 'Closed'];
        } else if (tab === 'students') {
            categories = Array.from(new Set(currentDataset.map(item => item.branch).filter(Boolean)));
            statuses = ['Active', 'Exchange Student'];
        } else if (tab === 'faqs') {
            categories = Array.from(new Set(currentDataset.map(item => item.category).filter(Boolean)));
            statuses = ['Important', 'General'];
        }

        // Rebuild Category options
        this.elements.categorySelect.innerHTML = `<option value="ALL">All Categories (${categories.length})</option>` +
            categories.map(c => `<option value="${c}" ${AppState.filterOptions.category === c ? 'selected' : ''}>${c}</option>`).join('');

        // Rebuild Status options
        this.elements.statusSelect.innerHTML = `<option value="ALL">All Statuses</option>` +
            statuses.map(s => {
                const val = tab === 'faqs' ? s.toUpperCase() : s;
                return `<option value="${val}" ${AppState.filterOptions.status === val ? 'selected' : ''}>${s}</option>`;
            }).join('');

        // Update Sort options to reflect current dataset fields
        if (this.elements.sortSelect) {
            let sortOptionsHtml = `<option value="default">Default Order</option>`;
            if (tab === 'events') {
                sortOptionsHtml += `
                    <option value="title" ${AppState.filterOptions.sortBy === 'title' ? 'selected' : ''}>Sort by Title</option>
                    <option value="date" ${AppState.filterOptions.sortBy === 'date' ? 'selected' : ''}>Sort by Date</option>
                    <option value="rating" ${AppState.filterOptions.sortBy === 'rating' ? 'selected' : ''}>Sort by Rating</option>
                    <option value="seats" ${AppState.filterOptions.sortBy === 'seats' ? 'selected' : ''}>Sort by Registrations</option>
                `;
            } else if (tab === 'students') {
                sortOptionsHtml += `
                    <option value="name" ${AppState.filterOptions.sortBy === 'name' ? 'selected' : ''}>Sort by Name</option>
                    <option value="gpa" ${AppState.filterOptions.sortBy === 'gpa' ? 'selected' : ''}>Sort by GPA</option>
                    <option value="semester" ${AppState.filterOptions.sortBy === 'semester' ? 'selected' : ''}>Sort by Semester</option>
                `;
            } else if (tab === 'faqs') {
                sortOptionsHtml += `
                    <option value="views" ${AppState.filterOptions.sortBy === 'views' ? 'selected' : ''}>Sort by Popularity (Views)</option>
                    <option value="question" ${AppState.filterOptions.sortBy === 'question' ? 'selected' : ''}>Sort by Question</option>
                `;
            }
            this.elements.sortSelect.innerHTML = sortOptionsHtml;
        }
    },

    updateBadges(isOffline, fromCache, timestamp) {
        if (this.elements.statusBadge) {
            if (isOffline) {
                this.elements.statusBadge.className = 'status-badge offline';
                this.elements.statusBadge.innerHTML = `<span class="pulse-dot"></span> Offline`;
            } else {
                this.elements.statusBadge.className = 'status-badge online';
                this.elements.statusBadge.innerHTML = `<span class="pulse-dot"></span> Live API`;
            }
        }

        if (this.elements.cacheBadge) {
            if (fromCache) {
                const dateStr = timestamp ? new Date(timestamp).toLocaleTimeString() : 'Local';
                this.elements.cacheBadge.className = 'status-badge cached';
                this.elements.cacheBadge.innerHTML = `💾 Cached (${dateStr})`;
                this.elements.cacheBadge.style.display = 'inline-flex';
            } else {
                this.elements.cacheBadge.className = 'status-badge cached';
                this.elements.cacheBadge.innerHTML = `💾 Saved to LocalStorage`;
                this.elements.cacheBadge.style.display = 'inline-flex';
            }
        }
    }
};

// ==========================================
// 5. Dependent Dropdowns Controller (Country -> State -> City)
// ==========================================
const LocationController = {
    geoData: [],

    init(geoLocations) {
        this.geoData = geoLocations || [];
        this.populateCountries();
        this.bindEvents();
    },

    populateCountries() {
        const select = UI.elements.countrySelect;
        if (!select) return;
        select.innerHTML = `<option value="">-- Select Country --</option>` +
            this.geoData.map(c => `<option value="${c.country}">${c.country} (${c.countryCode})</option>`).join('');

        this.resetStates();
        this.resetCities();
    },

    populateStates(countryName) {
        const select = UI.elements.stateSelect;
        if (!select) return;
        const country = this.geoData.find(c => c.country === countryName);
        if (!country || !country.states) {
            this.resetStates();
            this.resetCities();
            return;
        }

        select.innerHTML = `<option value="">-- Select State / Province --</option>` +
            country.states.map(s => `<option value="${s.stateName}">${s.stateName}</option>`).join('');
        select.disabled = false;
        this.resetCities();
    },

    populateCities(countryName, stateName) {
        const select = UI.elements.citySelect;
        if (!select) return;
        const country = this.geoData.find(c => c.country === countryName);
        if (!country) {
            this.resetCities();
            return;
        }
        const state = country.states.find(s => s.stateName === stateName);
        if (!state || !state.cities) {
            this.resetCities();
            return;
        }

        select.innerHTML = `<option value="">-- Select City --</option>` +
            state.cities.map(city => `<option value="${city}">${city}</option>`).join('');
        select.disabled = false;
    },

    resetStates() {
        if (!UI.elements.stateSelect) return;
        UI.elements.stateSelect.innerHTML = `<option value="">-- Select Country First --</option>`;
        UI.elements.stateSelect.disabled = true;
    },

    resetCities() {
        if (!UI.elements.citySelect) return;
        UI.elements.citySelect.innerHTML = `<option value="">-- Select State First --</option>`;
        UI.elements.citySelect.disabled = true;
    },

    bindEvents() {
        UI.elements.countrySelect?.addEventListener('change', (e) => {
            const country = e.target.value;
            if (country) {
                this.populateStates(country);
            } else {
                this.resetStates();
                this.resetCities();
            }
            this.updateSummary();
        });

        UI.elements.stateSelect?.addEventListener('change', (e) => {
            const country = UI.elements.countrySelect.value;
            const state = e.target.value;
            if (country && state) {
                this.populateCities(country, state);
            } else {
                this.resetCities();
            }
            this.updateSummary();
        });

        UI.elements.citySelect?.addEventListener('change', () => {
            this.updateSummary();
        });

        document.querySelector('#filterStudentsByLocationBtn')?.addEventListener('click', () => {
            const city = UI.elements.citySelect?.value;
            const state = UI.elements.stateSelect?.value;
            const country = UI.elements.countrySelect?.value;

            if (!country) {
                UI.showToast('Please select at least a country to filter students.');
                return;
            }

            // Switch to students tab and filter by chosen location
            App.switchTab('students');
            const targetQuery = city || state || country;
            UI.elements.searchInput.value = targetQuery;
            AppState.filterOptions.searchQuery = targetQuery;
            App.applyFilters();
            UI.showToast(`Showing students in ${targetQuery}`);
        });
    },

    updateSummary() {
        const country = UI.elements.countrySelect?.value || 'None';
        const state = UI.elements.stateSelect?.value || 'None';
        const city = UI.elements.citySelect?.value || 'None';

        if (UI.elements.locationStats) {
            UI.elements.locationStats.innerHTML = `
                <div class="geo-selection-text">
                    Currently Selected: <b>${country}</b> &rsaquo; <b>${state}</b> &rsaquo; <b>${city}</b>
                </div>
            `;
        }
    }
};

// ==========================================
// 6. Master Application Controller
// ==========================================
const App = {
    async init() {
        this.bindEvents();
        this.initThemeAndShell();
        this.listenNetwork();
        await this.loadAllDatasets();
        this.switchTab('events');
    },

    initThemeAndShell() {
        // Synchronize dark theme with previous practicals
        const themeToggle = document.querySelector('#themeToggle');
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark-theme');
            if (themeToggle) themeToggle.checked = true;
        }

        themeToggle?.addEventListener('change', () => {
            const isDark = themeToggle.checked;
            document.body.classList.toggle('dark-theme', isDark);
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });

        // Mobile Hamburger menu toggle
        const menuButton = document.querySelector('#menuButton');
        const navMenu = document.querySelector('#navMenu');
        menuButton?.addEventListener('click', () => {
            const isOpen = navMenu.classList.toggle('show');
            menuButton.setAttribute('aria-expanded', String(isOpen));
            menuButton.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        });

        // Dismiss notification banner
        const dismiss = document.querySelector('#dismissNotification');
        dismiss?.addEventListener('click', () => {
            document.querySelector('#notification')?.classList.add('hidden');
        });
    },

    listenNetwork() {
        window.addEventListener('online', () => {
            AppState.isOffline = false;
            UI.updateBadges(false, AppState.isUsingCache, AppState.cacheTimestamp);
            UI.showToast('Network connection restored. Online!');
        });

        window.addEventListener('offline', () => {
            AppState.isOffline = true;
            UI.updateBadges(true, true, AppState.cacheTimestamp);
            UI.showToast('You are now offline. Serving cached JSON data.');
        });
    },

    async loadAllDatasets() {
        UI.renderSkeletons();
        try {
            // Load Events
            const evRes = await DataService.fetchData('events.json', 'events');
            AppState.rawDatasets.events = evRes.data;

            // Load Students
            const stuRes = await DataService.fetchData('students.json', 'students');
            AppState.rawDatasets.students = stuRes.data;

            // Load FAQs
            const faqRes = await DataService.fetchData('faqs.json', 'faqs');
            AppState.rawDatasets.faqs = faqRes.data;

            // Load Geodata (from api.json)
            const apiRes = await DataService.fetchData('api.json', 'api_master');
            AppState.rawDatasets.geo = apiRes.data.geoLocations || [];

            AppState.isUsingCache = evRes.fromCache || stuRes.fromCache;
            AppState.cacheTimestamp = evRes.timestamp;

            UI.updateBadges(AppState.isOffline, AppState.isUsingCache, AppState.cacheTimestamp);
            LocationController.init(AppState.rawDatasets.geo);

            // Update Tab Badges
            document.querySelector('#eventsCountBadge').textContent = AppState.rawDatasets.events.length;
            document.querySelector('#studentsCountBadge').textContent = AppState.rawDatasets.students.length;
            document.querySelector('#faqsCountBadge').textContent = AppState.rawDatasets.faqs.length;

        } catch (err) {
            console.error('Fatal initialization error:', err);
            UI.renderError(err.message, () => App.loadAllDatasets());
        }
    },

    async refreshCache() {
        UI.showToast('Syncing latest JSON from external files...');
        await this.loadAllDatasets();
        this.applyFilters();
        UI.showToast('Datasets successfully refreshed and cached in LocalStorage!');
    },

    bindEvents() {
        // Tab switching
        UI.elements.tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.getAttribute('data-tab');
                this.switchTab(tab);
            });
        });

        // Search Input (Real-time with debouncing)
        let debounceTimer;
        UI.elements.searchInput?.addEventListener('input', (e) => {
            const query = e.target.value;
            UI.elements.clearSearchBtn.style.display = query ? 'block' : 'none';
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                AppState.filterOptions.searchQuery = query;
                AppState.filterOptions.currentPage = 1; // Reset to page 1 on search
                this.applyFilters();
            }, 250);
        });

        // Clear search button
        UI.elements.clearSearchBtn?.addEventListener('click', () => {
            UI.elements.searchInput.value = '';
            UI.elements.clearSearchBtn.style.display = 'none';
            AppState.filterOptions.searchQuery = '';
            AppState.filterOptions.currentPage = 1;
            this.applyFilters();
        });

        // Category Filter
        UI.elements.categorySelect?.addEventListener('change', (e) => {
            AppState.filterOptions.category = e.target.value;
            AppState.filterOptions.currentPage = 1;
            this.applyFilters();
        });

        // Status Filter
        UI.elements.statusSelect?.addEventListener('change', (e) => {
            AppState.filterOptions.status = e.target.value;
            AppState.filterOptions.currentPage = 1;
            this.applyFilters();
        });

        // Sort By Select
        UI.elements.sortSelect?.addEventListener('change', (e) => {
            AppState.filterOptions.sortBy = e.target.value;
            this.applyFilters();
        });

        // Sort Order Toggle (Ascending / Descending)
        UI.elements.sortOrderBtn?.addEventListener('click', () => {
            const newOrder = AppState.filterOptions.sortOrder === 'asc' ? 'desc' : 'asc';
            AppState.filterOptions.sortOrder = newOrder;
            UI.elements.sortOrderBtn.textContent = newOrder === 'asc' ? '▲ Asc' : '▼ Desc';
            UI.elements.sortOrderBtn.setAttribute('title', `Currently ${newOrder.toUpperCase()}`);
            this.applyFilters();
        });

        // Page Size (Items Per Page)
        UI.elements.pageSizeSelect?.addEventListener('change', (e) => {
            AppState.filterOptions.pageSize = e.target.value;
            AppState.filterOptions.currentPage = 1;
            this.applyFilters();
        });

        // Reset Filters Button
        UI.elements.resetFiltersBtn?.addEventListener('click', () => {
            this.resetFilters();
        });

        // Manual Sync / Refresh Button
        UI.elements.syncBtn?.addEventListener('click', () => {
            this.refreshCache();
        });

        // Accordion Event Delegation for FAQs
        UI.elements.container?.addEventListener('click', (e) => {
            const header = e.target.closest('.faq-item-header');
            if (!header) return;
            const item = header.closest('.faq-item');
            const isExpanded = item.classList.toggle('expanded');
            header.setAttribute('aria-expanded', String(isExpanded));
        });
    },

    switchTab(tab) {
        AppState.currentTab = tab;
        UI.elements.tabButtons.forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-tab') === tab);
        });

        const locationSection = document.querySelector('#locationDropdownSection');
        const toolbarSection = document.querySelector('#p6Toolbar');

        if (tab === 'locations') {
            if (locationSection) locationSection.style.display = 'block';
            if (toolbarSection) toolbarSection.style.display = 'none';
            if (UI.elements.container) UI.elements.container.innerHTML = '';
            if (UI.elements.paginationArea) UI.elements.paginationArea.innerHTML = '';
            if (UI.elements.metricsCount) UI.elements.metricsCount.innerHTML = 'Location Directory Explorer';
            return;
        }

        if (locationSection) locationSection.style.display = 'none';
        if (toolbarSection) toolbarSection.style.display = 'flex';

        // Reset page and re-populate filter dropdowns for this dataset
        AppState.filterOptions.currentPage = 1;
        const currentData = AppState.rawDatasets[tab] || [];
        UI.updateFilterDropdowns(tab, currentData);
        this.applyFilters();
    },

    resetFilters() {
        AppState.filterOptions.searchQuery = '';
        AppState.filterOptions.category = 'ALL';
        AppState.filterOptions.status = 'ALL';
        AppState.filterOptions.sortBy = 'default';
        AppState.filterOptions.sortOrder = 'asc';
        AppState.filterOptions.currentPage = 1;

        if (UI.elements.searchInput) UI.elements.searchInput.value = '';
        if (UI.elements.clearSearchBtn) UI.elements.clearSearchBtn.style.display = 'none';
        if (UI.elements.categorySelect) UI.elements.categorySelect.value = 'ALL';
        if (UI.elements.statusSelect) UI.elements.statusSelect.value = 'ALL';
        if (UI.elements.sortSelect) UI.elements.sortSelect.value = 'default';
        if (UI.elements.sortOrderBtn) UI.elements.sortOrderBtn.textContent = '▲ Asc';

        this.applyFilters();
        UI.showToast('Filters reset to default.');
    },

    goToPage(pageNumber) {
        AppState.filterOptions.currentPage = pageNumber;
        this.applyFilters();
        // Smooth scroll to container top
        UI.elements.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    applyFilters() {
        const { currentTab, rawDatasets, filterOptions } = AppState;
        const sourceList = rawDatasets[currentTab] || [];

        if (sourceList.length === 0) {
            UI.renderEmpty();
            return;
        }

        // 1. Search Array
        const searched = ArrayOps.search(sourceList, filterOptions.searchQuery, currentTab);

        // 2. Filter Array
        const filtered = ArrayOps.filter(searched, filterOptions.category, filterOptions.status, currentTab);

        // 3. Sort Array
        const sorted = ArrayOps.sort(filtered, filterOptions.sortBy, filterOptions.sortOrder, currentTab);

        // 4. Paginate Array
        const pagination = ArrayOps.paginate(sorted, filterOptions.currentPage, filterOptions.pageSize);

        // Update metrics text
        if (UI.elements.metricsCount) {
            if (pagination.totalItems === 0) {
                UI.elements.metricsCount.innerHTML = `Found <b>0</b> records matching your criteria.`;
            } else {
                UI.elements.metricsCount.innerHTML = `Showing <b>${pagination.startIndex} - ${pagination.endIndex}</b> of <b>${pagination.totalItems}</b> records (${sourceList.length} total)`;
            }
        }

        // Render Results
        if (pagination.totalItems === 0) {
            UI.renderEmpty();
            return;
        }

        let html = '';
        if (currentTab === 'events') {
            html = UI.renderEventCards(pagination.items);
        } else if (currentTab === 'students') {
            html = UI.renderStudentCards(pagination.items);
        } else if (currentTab === 'faqs') {
            html = UI.renderFaqList(pagination.items);
        }

        UI.elements.container.innerHTML = html;
        UI.renderPaginationControls(pagination);
    }
};

// ==========================================
// 7. Boot Application on DOM Ready
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});
