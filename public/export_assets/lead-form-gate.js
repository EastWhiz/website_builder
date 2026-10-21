/*
 * Lead form submit gate for exported landing pages.
 *
 * ONE capture-phase submit listener on the document validates every lead form (with or without a phone
 * box) before anything is posted. Depends on: window.LeadValidation, window.intlTelInput (+ intlTelInputUtils).
 * Optional helpers defined by the page (used when present): syncTrackingParamsFromUrl, ensureUseAweberFlag,
 * ensureHoneypotInputs, handleOtpVerification.
 */
(function (global) {
    "use strict";

    var LV = global.LeadValidation;

    // SweetAlert2 comes from a CDN. If it is blocked, the page's own Swal.fire() calls (OTP steps, the
    // server-side api_error message after a redirect) would throw. Provide a minimal fallback so the visitor
    // still sees the message and nothing breaks.
    if (!global.Swal) {
        global.Swal = {
            fire: function (options) {
                var text = options && (options.text || options.title) ? String(options.text || options.title) : "";
                if (text && typeof global.alert === "function") global.alert(text);
                return Promise.resolve({ isConfirmed: false, isDismissed: true });
            }
        };
    }

    var READY_TIMEOUT_MS = 5000;
    var FETCH_TIMEOUT_MS = 3000;

    var itiByInput = new WeakMap();
    var initialised = new WeakSet();
    var errorSeq = 0;
    var stylesAdded = false;

    function addStyles() {
        if (stylesAdded) return;
        stylesAdded = true;
        var style = document.createElement("style");
        style.textContent =
            ".lf-error{color:#c0392b;font-size:13px;line-height:1.35;margin-top:4px;text-align:left}" +
            ".lf-invalid{border-color:#c0392b !important}" +
            ".lf-suggest{background:none;border:0;color:#0b5ed7;text-decoration:underline;cursor:pointer;padding:0;margin-left:6px;font:inherit}";
        (document.head || document.documentElement).appendChild(style);
    }

    /* ---------- helpers ---------- */
    function isLeadForm(form) {
        if (!form || form.tagName !== "FORM") return false;
        return !!(form.querySelector('input[name="email"], input[name="temp_phone"], input.telInputs') || form.id === "myForm");
    }

    function fields(form) {
        return {
            first: form.querySelector('input[name="firstname"]'),
            last: form.querySelector('input[name="lastname"]'),
            email: form.querySelector('input[name="email"]'),
            phone: form.querySelector("input.telInputs") || form.querySelector('input[name="temp_phone"]')
        };
    }

    function setHidden(form, name, value) {
        var el = form.querySelector('input[name="' + name + '"]');
        if (!el) {
            el = document.createElement("input");
            el.type = "hidden";
            el.name = name;
            form.appendChild(el);
        }
        el.value = value == null ? "" : String(value);
    }

    function formLanguage(form) {
        if (form.dataset.leadLang) return form.dataset.leadLang;
        // Only an explicit data-form-language counts. The hidden form_language field is written by this script
        // itself, so reading it back would just lock in whatever was detected first.
        var explicit = (form.dataset.formLanguage || "").trim();
        var lang;
        if (explicit && LV.languages.indexOf(explicit) !== -1) {
            lang = explicit;
        } else {
            var placeholders = [];
            form.querySelectorAll("input").forEach(function (i) { if (i.placeholder) placeholders.push(i.placeholder); });
            lang = LV.detectLanguage(placeholders);
        }
        form.dataset.leadLang = lang;
        return lang;
    }

    function msg(form, key, sub) {
        return LV.message(formLanguage(form), key, sub);
    }

    function withTimeout(promise, ms) {
        return new Promise(function (resolve) {
            var done = false;
            var t = setTimeout(function () { if (!done) { done = true; resolve(undefined); } }, ms);
            promise.then(function (v) { if (!done) { done = true; clearTimeout(t); resolve(v); } },
                function () { if (!done) { done = true; clearTimeout(t); resolve(undefined); } });
        });
    }

    function fetchJson(url) {
        if (typeof fetch !== "function") return Promise.resolve(undefined);
        var controller = typeof AbortController === "function" ? new AbortController() : null;
        var p = fetch(url, controller ? { signal: controller.signal } : undefined).then(function (r) { return r.json(); });
        return withTimeout(p, FETCH_TIMEOUT_MS).then(function (v) {
            if (v === undefined && controller) controller.abort();
            return v;
        });
    }

    /* ---------- inline errors ---------- */
    function anchorFor(input) {
        return input.closest(".iti") || input;
    }

    function clearError(input) {
        if (!input) return;
        input.classList.remove("lf-invalid");
        input.removeAttribute("aria-invalid");
        var id = input.dataset.lfErr;
        if (id) {
            var el = document.getElementById(id);
            if (el && el.parentNode) el.parentNode.removeChild(el);
            input.removeAttribute("aria-describedby");
            delete input.dataset.lfErr;
        }
    }

    function showError(input, message, suggestion, form) {
        if (!input) return;
        addStyles();
        clearError(input);
        var el = document.createElement("div");
        el.className = "lf-error";
        el.setAttribute("role", "alert");
        el.id = "lf-err-" + (++errorSeq);
        el.textContent = message;
        if (suggestion) {
            var btn = document.createElement("button");
            btn.type = "button";
            btn.className = "lf-suggest";
            btn.textContent = LV.message(formLanguage(form), "email_use", suggestion);
            btn.addEventListener("click", function () {
                input.value = suggestion;
                clearError(input);
                input.focus();
            });
            el.appendChild(btn);
        }
        var anchor = anchorFor(input);
        anchor.parentNode.insertBefore(el, anchor.nextSibling);
        input.classList.add("lf-invalid");
        input.setAttribute("aria-invalid", "true");
        input.setAttribute("aria-describedby", el.id);
        input.dataset.lfErr = el.id;
    }

    /* ---------- per-field validation (returns true when the field passes) ---------- */
    function checkName(form, input) {
        if (!input) return true;
        var r = LV.validateName(input.value);
        if (!r.ok) {
            showError(input, msg(form, r.code === "required" ? "name_required" : "name_invalid"), null, form);
            return false;
        }
        input.value = r.value;
        clearError(input);
        return true;
    }

    function checkEmail(form, input, fromBlur) {
        if (!input) return true;
        var r = LV.validateEmail(input.value);
        if (!r.ok) {
            showError(input, msg(form, LV.emailMessageKey(r.code)), r.code === "tld" ? r.suggestion : null, form);
            return false;
        }
        input.value = r.value;
        if (r.suggestion) {
            // Soft block: warn once. On submit the first attempt stops; the same value goes through next time.
            var first = form.dataset.lfSuggestShown !== r.value;
            form.dataset.lfSuggestShown = r.value;
            if (first || fromBlur === true) showError(input, msg(form, "email_suggest", r.suggestion), r.suggestion, form);
            return !(first && fromBlur !== true);
        }
        clearError(input);
        return true;
    }

    function checkPhone(form, input) {
        if (!input) return { ok: true };
        var iti = itiByInput.get(input);
        var r = LV.validatePhone(iti, input.value);
        if (!r.ok) {
            showError(input, msg(form, LV.phoneMessageKey(r.code)), null, form);
            return r;
        }
        clearError(input);
        return r;
    }

    /* ---------- phone widget ---------- */
    function initPhone(form, input) {
        if (!input || itiByInput.has(input) || typeof global.intlTelInput !== "function") return;
        // No default country: defaultToFirstCountry:false stops the library silently preselecting the first
        // country in its list when nothing else is known. The visitor (or the IP lookup) chooses one.
        var iti = global.intlTelInput(input, { nationalMode: false, defaultToFirstCountry: false });
        itiByInput.set(input, iti);
        input.setAttribute("autocomplete", "tel");
        input.setAttribute("inputmode", "tel");
        input.setAttribute("maxlength", "24");

        input.addEventListener("countrychange", function () {
            if (!input.dataset.lfAuto) input.dataset.lfChosen = "1";
            if (input.dataset.lfErr) clearError(input);
        });
        input.addEventListener("input", function () {
            var cleaned = LV.cleanPhoneInput(input.value);
            if (cleaned !== input.value.trim() && /[^0-9+\s().\-]/.test(input.value)) input.value = cleaned;
            if (input.dataset.lfErr) checkPhone(form, input);
        });
        input.addEventListener("blur", function () {
            if (input.value.trim() !== "") checkPhone(form, input);
        });

        // Country from IP: best effort, never blocks anything, never overrides a choice the visitor made.
        fetchJson("https://ipapi.co/json/").then(function (data) {
            var code = data && typeof data.country_code === "string" ? data.country_code.trim().toLowerCase() : "";
            if (!/^[a-z]{2}$/.test(code) || input.dataset.lfChosen === "1" || input.value.trim() !== "") return;
            input.dataset.lfAuto = "1";
            try { iti.setCountry(code); } catch (e) { /* ignore */ }
            delete input.dataset.lfAuto;
        });
    }

    function setupForm(form) {
        if (initialised.has(form)) return;
        initialised.add(form);
        formLanguage(form); // cache before intl-tel-input can touch placeholders
        var f = fields(form);
        // Any edit after validation (or after an OTP was verified) invalidates both, so changed data is
        // validated again and, on OTP pages, verified again. Programmatic value changes do not fire "input".
        form.addEventListener("input", function (event) {
            var t = event.target;
            if (t === f.first || t === f.last || t === f.email || t === f.phone) {
                delete form.dataset.leadValidated;
                delete form.dataset.otpVerified;
            }
        });
        // Turn native validation off only now that our own is running; if this script never loads, the
        // browser's own checks (and the server) still apply.
        form.setAttribute("novalidate", "novalidate");
        if (f.email) {
            f.email.setAttribute("autocomplete", "email");
            f.email.setAttribute("inputmode", "email");
            f.email.setAttribute("autocapitalize", "off");
            f.email.setAttribute("spellcheck", "false");
            f.email.setAttribute("maxlength", "254");
            f.email.addEventListener("blur", function () { if (f.email.value.trim() !== "") checkEmail(form, f.email, true); });
            f.email.addEventListener("input", function () {
                if (f.email.dataset.lfErr && LV.validateEmail(f.email.value).ok) clearError(f.email);
            });
        }
        [f.first, f.last].forEach(function (input) {
            if (!input) return;
            input.addEventListener("blur", function () { if (input.value.trim() !== "") checkName(form, input); });
            input.addEventListener("input", function () {
                if (input.dataset.lfErr && LV.validateName(input.value).ok) clearError(input);
            });
        });
        initPhone(form, f.phone);
    }

    /* ---------- readiness ---------- */
    function whenReady(form) {
        var f = fields(form);
        if (!f.phone) return Promise.resolve(true);
        var iti = itiByInput.get(f.phone);
        if (!iti) return Promise.resolve(false);
        var utilsLoaded = function () { return !!global.intlTelInputUtils; };
        if (utilsLoaded()) return Promise.resolve(true);
        var ready = iti.promise ? iti.promise.then(function () { return utilsLoaded(); }, function () { return false; }) : Promise.resolve(false);
        return withTimeout(ready, READY_TIMEOUT_MS).then(function (v) { return v === true; });
    }

    /* ---------- submit ---------- */
    function setBusy(form, busy) {
        var btn = form.querySelector('[type="submit"]');
        if (busy) {
            form.dataset.lfBusy = "1";
            if (btn) {
                if (!btn.dataset.original) btn.dataset.original = btn.innerHTML;
                btn.innerHTML = '<div class="loader"></div>';
                btn.style.opacity = "0.6";
                btn.disabled = true;
            }
        } else {
            delete form.dataset.lfBusy;
            if (btn) {
                if (btn.dataset.original) btn.innerHTML = btn.dataset.original;
                btn.style.opacity = "1";
                btn.disabled = false;
            }
        }
    }

    function submitValidated(form) {
        form.dataset.leadValidated = "true";
        HTMLFormElement.prototype.submit.call(form); // not form.submit(): a field named "submit" would shadow it
    }

    function call(name, form) {
        if (typeof global[name] === "function") {
            try { global[name](form); } catch (e) { /* page helper failed; never block the submit gate */ }
        }
    }

    async function run(form) {
        if (form.dataset.lfBusy === "1") return;
        setupForm(form);

        var modal = document.getElementById("otpModal");
        if (modal && modal.style.display !== "none" && form.dataset.otpVerified !== "true") {
            var otpError = document.getElementById("otpError");
            if (otpError) otpError.textContent = "Please complete OTP verification first.";
            return;
        }

        setBusy(form, true);
        try {
            var ready = await whenReady(form);
            var f = fields(form);
            var ok = true;
            var firstBad = null;
            var note = function (input, passed) { if (!passed) { ok = false; if (!firstBad) firstBad = input; } };

            note(f.first, checkName(form, f.first));
            note(f.last, checkName(form, f.last));
            note(f.email, checkEmail(form, f.email, false));

            var phoneResult = { ok: true };
            if (f.phone) {
                phoneResult = ready ? checkPhone(form, f.phone) : { ok: false, code: "not_ready" };
                if (!ready) showError(f.phone, msg(form, "not_ready"), null, form);
                note(f.phone, phoneResult.ok);
            }

            // Keep the browser's own required/pattern checks for any other field the editor added.
            if (ok) {
                var managed = [f.first, f.last, f.email, f.phone];
                var other = Array.prototype.filter.call(form.elements, function (el) {
                    return el.willValidate && managed.indexOf(el) === -1 && !el.checkValidity();
                })[0];
                if (other) { ok = false; firstBad = other; try { other.reportValidity(); } catch (e) { /* ignore */ } }
            }

            if (!ok) {
                setBusy(form, false);
                if (firstBad) {
                    try { firstBad.focus({ preventScroll: false }); } catch (e) { firstBad.focus(); }
                }
                return;
            }

            // Everything below only happens for a fully valid form.
            if (f.phone) {
                setHidden(form, "phone", phoneResult.value);
                setHidden(form, "country", phoneResult.iso2 || "");
                setHidden(form, "area_code", phoneResult.dialCode || "");
                var rawLang = (navigator.language || navigator.userLanguage || "en");
                var primary = (String(rawLang).split(/[-_]/)[0] || "en").replace(/[^a-zA-Z]/g, "");
                setHidden(form, "lang", (primary.length >= 2 ? primary.substring(0, 2) : "en").toUpperCase());
                var ipData = await fetchJson("https://ipinfo.io/json");
                setHidden(form, "userip", ipData && ipData.ip ? ipData.ip : "");
            }
            setHidden(form, "form_language", formLanguage(form));
            if (!form.querySelector('input[name="is_self_hosted"]')) setHidden(form, "is_self_hosted", "false");
            call("syncTrackingParamsFromUrl", form);
            call("ensureUseAweberFlag", form);
            call("ensureHoneypotInputs", form);

            var otpService = form.querySelector('[name="otp_service_id"]');
            if (otpService && String(otpService.value || "").trim() !== "" && typeof global.handleOtpVerification === "function") {
                // The data is valid. After the visitor verifies the code the page calls form.requestSubmit(), which
                // fires a normal submit event; leadValidated tells the gate to let that one through.
                form.dataset.leadValidated = "true";
                await global.handleOtpVerification(form); // returns once the modal is up (or OTP generation failed)
                setBusy(form, false); // closing the modal must not leave the form stuck
                return;
            }
            submitValidated(form);
        } catch (err) {
            if (global.console) console.error("Lead form gate error:", err);
            setBusy(form, false);
            var f2 = fields(form);
            var target = f2.phone || f2.email;
            if (target) showError(target, msg(form, "not_ready"), null, form);
        }
    }

    // Registered the moment this script runs (capture phase), so no lead form can submit unchecked.
    document.addEventListener("submit", function (event) {
        var form = event.target;
        if (!isLeadForm(form)) return;
        if (form.dataset.leadValidated === "true" && form.dataset.otpVerified === "true") return;
        event.preventDefault();
        event.stopImmediatePropagation();
        run(form);
    }, true);

    // Back/forward cache can restore the page with a form still in its loading state; reset it.
    window.addEventListener("pageshow", function (event) {
        if (!event.persisted) return;
        document.querySelectorAll("form").forEach(function (form) {
            if (isLeadForm(form) && form.dataset.lfBusy === "1") setBusy(form, false);
        });
    });

    function init() {
        document.querySelectorAll("form").forEach(function (form) { if (isLeadForm(form)) setupForm(form); });
    }

    global.LeadFormGate = { init: init, setupForm: setupForm, run: run, submitValidated: submitValidated };
})(window);
