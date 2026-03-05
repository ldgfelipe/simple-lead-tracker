(function() {
    // --- INTERCEPTOR DE PIPEDRIVE (Monkey Patching) ---
    const originalFetch = window.fetch;
    window.fetch = async (...args) => {
        const url = args[0];
        const options = args[1];

        if (typeof url === 'string' && url.includes('pipedrive.com/f/')) {
            if (url.includes('interacted')) {
                if (typeof window.slt_fire === 'function') {
                    window.slt_fire({ e: 'Pipedrive: Interacción con Formulario Detectada', u: 'Prospecto Iniciando' });
                }
            } 
            else if (options && options.body) {
                try {
                    const payload = JSON.parse(options.body);
                    let extractedName = "";
                    let extractedEmail = "";
                    let extractedPhone = "";
                    let allFields = [];

                    // Función recursiva para encontrar datos en cualquier nivel del JSON
                    const searchDeep = (obj) => {
                        for (let key in obj) {
                            let val = obj[key];
                            if (typeof val === 'object' && val !== null) {
                                searchDeep(val);
                            } else if (val && val !== "*") {
                                let cleanVal = String(val).trim();
                                if (cleanVal.length < 2) continue;
                                
                                // 1. Identificar Email
                                if (cleanVal.includes('@') && !extractedEmail) {
                                    extractedEmail = cleanVal;
                                    allFields.push(cleanVal);
                                } 
                                // 2. Identificar Teléfono (mínimo 7 dígitos numéricos)
                                else if (cleanVal.replace(/[^0-9]/g, "").length >= 7 && /^[0-9\s\+\-\(\)]+$/.test(cleanVal) && !extractedPhone) {
                                    extractedPhone = cleanVal;
                                    allFields.push(cleanVal);
                                }
                                // 3. Identificar Nombre (si tiene espacios y no es email/tel)
                                else if (cleanVal.includes(' ') && cleanVal.length > 3 && !extractedName && !cleanVal.includes('@')) {
                                    extractedName = cleanVal;
                                    allFields.push(cleanVal);
                                } else {
                                    allFields.push(cleanVal);
                                }
                            }
                        }
                    };

                    searchDeep(payload);

                    if (typeof window.slt_fire === 'function') {
                        let identityParts = [];
                        if (extractedName) identityParts.push(extractedName);
                        if (extractedEmail) identityParts.push(`<${extractedEmail}>`);
                        if (extractedPhone) identityParts.push(`[Tel: ${extractedPhone}]`);

                        const identityBase = identityParts.length > 0 ? identityParts.join(' ') : "Lead Pipedrive";
                        const fullData = allFields.length > 0 ? identityBase + ' | Todo: ' + [...new Set(allFields)].join(', ') : identityBase;

                        window.slt_fire({ 
                            e: 'Pipedrive: Formulario Enviado (Network)',
                            u: fullData
                        });
                    }
                } catch (e) {
                    if (typeof window.slt_fire === 'function') {
                        window.slt_fire({ e: 'Pipedrive: Envío de Red Detectado', u: 'Analizando Datos...' });
                    }
                }
            }
        }
        return originalFetch(...args);
    };

    const startTracker = () => {
        const startTime = Date.now();
        
        const getAgent = () => {
            const ua = navigator.userAgent;
            if (ua.indexOf("Chrome") > -1) return "Chrome";
            if (ua.indexOf("Firefox") > -1) return "Firefox";
            if (ua.indexOf("Safari") > -1) return "Safari";
            return "Browser";
        };

        const screenRes = window.screen.width + "x" + window.screen.height;
        const sessionID = sessionStorage.getItem('slt_sid') || 's_' + getAgent() + '_' + screenRes + '_' + Math.random().toString(36).substr(2, 9);
        sessionStorage.setItem('slt_sid', sessionID);

        const getMeta = () => {
            const urlParams = new URLSearchParams(window.location.search);
            return {
                l: navigator.language || 'n/a',
                z: Intl.DateTimeFormat().resolvedOptions().timeZone || 'n/a',
                p: navigator.platform || 'n/a',
                utm: urlParams.get('utm_source') || urlParams.get('source') || '',
                res: screenRes
            };
        };
        const meta = getMeta();

        const currentPath = window.location.pathname;
        const externalReferrer = document.referrer;
        
        if (!sessionStorage.getItem('slt_original_ref') && externalReferrer && !externalReferrer.includes(window.location.hostname)) {
            sessionStorage.setItem('slt_original_ref', externalReferrer);
        }

        const sessionReferrer = sessionStorage.getItem('slt_last_page') || 
                                sessionStorage.getItem('slt_original_ref') || 
                                "Directo";

        sessionStorage.setItem('slt_last_page', currentPath);

        let slt_last_e = "";
        let slt_last_t = 0;

        const isUrlAllowed = () => {
            if (slt_vars.strict_mode !== '1') return true;
            const current = slt_vars.current_clean_path;
            const rules = slt_vars.tracking_rules || [];
            return rules.some(page => {
                if (page.url === '*') return true;
                let target = page.url.split('?')[0].replace(/^\/|\/$/g, "");
                if (target === "") target = "home_root";
                return current === target;
            });
        };

        window.slt_fire = (params) => {
            if (!isUrlAllowed()) return;
            const now = Date.now();
            if (params.e && params.e === slt_last_e && (now - slt_last_t) < 1500) return;
            
            slt_last_e = params.e || "";
            slt_last_t = now;

            window.requestAnimationFrame(() => {
                const data = new FormData();
                data.append('s', sessionID);
                data.append('url', window.location.pathname);
                data.append('hp_field', ''); 
                data.append('meta_lang', meta.l);
                data.append('meta_tz', meta.z);
                data.append('meta_utm', meta.utm);
                data.append('ref', params.ref || (params.e === 'Entrada' ? sessionReferrer : window.location.href));
                if (params.e) data.append('e', params.e);
                if (params.u) data.append('u', params.u);

                if (navigator.sendBeacon) {
                    navigator.sendBeacon(slt_vars.rest_url, data);
                }
            });
        };

        slt_fire({ e: 'Entrada' });

        window.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                const timeSpent = Math.round((Date.now() - startTime) / 1000);
                if (timeSpent > 2) slt_fire({ e: 'Salida | Tiempo: ' + timeSpent + 's' });
            }
        }, { passive: true });

        document.addEventListener('click', (e) => {
            if (!isUrlAllowed()) return;
            const target = e.target.closest('a, button, input[type="submit"], .slt-track');
            if (!target) return;
            const allPages = slt_vars.tracking_rules || [];
            let activeRules = [];
            allPages.forEach(page => {
                let pUrl = page.url.replace(/^\/|\/$/g, "");
                if (pUrl === "") pUrl = "home_root";
                if (page.url === '*' || slt_vars.current_clean_path === pUrl) {
                    activeRules = activeRules.concat(page.rules || []);
                }
            });
            let matchedName = null;
            for (let rule of activeRules) {
                if (rule.selector && target.closest(rule.selector)) {
                    matchedName = rule.name;
                    break;
                }
            }
            if (matchedName) {
                slt_fire({ e: 'Click: ' + matchedName });
            } else {
                const info = target.innerText.trim().substring(0, 25) || target.id || 'Elemento';
                slt_fire({ e: 'Click: ' + info });
            }
        }, { capture: true, passive: true });

        document.addEventListener('copy', () => {
            const sel = window.getSelection().toString().trim();
            if (sel.length < 5) return;
            let type = "Texto";
            if (sel.includes('@')) type = "Email";
            else if (/^[\d\s\+\-\(\)]{7,20}$/.test(sel)) type = "Teléfono";
            slt_fire({ e: 'Copiado (' + type + '): ' + sel.substring(0, 30) });
        }, { passive: true });
    };

    window.addEventListener("message", function(event) {
        if (event.origin.includes("pipedrive.com") || event.origin.includes(window.location.hostname)) {
            try {
                const payload = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                if (payload.event === 'form-submitted' || payload.type === 'form-submitted') {
                    const userIdent = payload.data?.name || payload.data?.email || "Lead Identificado";
                    slt_fire({ e: 'Pipedrive: Evento Detectado', u: userIdent });
                }
            } catch (e) {}
        }
    }, false);
    
    if (document.readyState === 'complete') {
        setTimeout(startTracker, 2000);
    } else {
        window.addEventListener('load', () => {
            if (window.requestIdleCallback) {
                window.requestIdleCallback(() => setTimeout(startTracker, 1000), { timeout: 3000 });
            } else {
                setTimeout(startTracker, 2500);
            }
        });
    }
})();