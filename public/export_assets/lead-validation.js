/*
 * Lead form validation rules (email, name, phone helpers) shared by exported landing pages and tests.
 * Browser: window.LeadValidation. Node: module.exports. No dependencies.
 * Server-side twin: public/api_files/lead_validation.php (keep rules identical; see tests/fixtures).
 * NOTE: embedded inline into exported pages from PHP, so avoid dollar-brace sequences in template strings.
 */
(function (root, factory) {
    var api = factory();
    if (typeof module === "object" && module.exports) {
        module.exports = api;
    } else {
        root.LeadValidation = api;
    }
})(typeof self !== "undefined" ? self : this, function () {
    "use strict";

    var data = {
        reservedDomains: [], reservedSuffixes: [], disposableDomains: [], hardTldTypos: [],
        tldTypoSuggestion: "com", typoDomains: {}, commonDomains: []
    };
    var reservedSet = {}, disposableSet = {}, hardTldSet = {}, commonSet = {};

    function toSet(list) {
        var set = {};
        for (var i = 0; i < list.length; i++) set[String(list[i]).toLowerCase()] = true;
        return set;
    }

    function configure(cfg) {
        cfg = cfg || {};
        for (var k in data) {
            if (Object.prototype.hasOwnProperty.call(cfg, k) && cfg[k] != null) data[k] = cfg[k];
        }
        reservedSet = toSet(data.reservedDomains);
        disposableSet = toSet(data.disposableDomains);
        hardTldSet = toSet(data.hardTldTypos);
        commonSet = toSet(data.commonDomains);
    }

    /* ---------- Normalisation ---------- */
    function normalizeText(value) {
        var s = value == null ? "" : String(value);
        if (typeof s.normalize === "function") s = s.normalize("NFKC");
        s = s.replace(/[​-‍⁠﻿­]/g, "");
        s = s.replace(/[\u0000-\u001F\u007F]/g, "");
        return s.trim();
    }

    /* ---------- Email ---------- */
    var LOCAL_RE = /^[a-z0-9._%+-]+$/;
    var LABEL_RE = /^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/;
    var TLD_RE = /^([a-z]{2,24}|xn--[a-z0-9-]{1,59})$/;

    function osaDistance(a, b) {
        var al = a.length, bl = b.length, i, j, d = [];
        for (i = 0; i <= al; i++) { d[i] = [i]; }
        for (j = 0; j <= bl; j++) { d[0][j] = j; }
        for (i = 1; i <= al; i++) {
            for (j = 1; j <= bl; j++) {
                var cost = a.charAt(i - 1) === b.charAt(j - 1) ? 0 : 1;
                d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + cost);
                if (i > 1 && j > 1 && a.charAt(i - 1) === b.charAt(j - 2) && a.charAt(i - 2) === b.charAt(j - 1)) {
                    d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
                }
            }
        }
        return d[al][bl];
    }

    function suggestDomain(domain) {
        if (!domain || commonSet[domain]) return null;
        if (Object.prototype.hasOwnProperty.call(data.typoDomains, domain)) return data.typoDomains[domain];
        if (domain.length < 6) return null;
        for (var i = 0; i < data.commonDomains.length; i++) {
            var c = data.commonDomains[i];
            if (Math.abs(c.length - domain.length) <= 1 && osaDistance(domain, c) === 1) return c;
        }
        return null;
    }

    function domainMatches(set, domain) {
        var parts = domain.split(".");
        for (var i = 0; i < parts.length - 1; i++) {
            if (set[parts.slice(i).join(".")]) return true;
        }
        return false;
    }

    /**
     * Returns {ok, value, code, suggestion}.
     * codes: required, space, chars, invalid, blocked, tld. suggestion (when ok) = soft "did you mean" domain.
     */
    function validateEmail(raw) {
        var v = normalizeText(raw);
        if (v === "") return { ok: false, value: "", code: "required" };
        if (/\s/.test(v)) return { ok: false, value: v, code: "space" };
        v = v.toLowerCase();
        if (v.length > 254) return { ok: false, value: v, code: "invalid" };
        var at = v.split("@");
        if (at.length !== 2) return { ok: false, value: v, code: "invalid" };
        var local = at[0], domain = at[1];
        if (local === "" || domain === "") return { ok: false, value: v, code: "invalid" };
        if (local.length > 64 || domain.length > 253) return { ok: false, value: v, code: "invalid" };
        if (!/^[\x21-\x7E]+$/.test(local + domain)) return { ok: false, value: v, code: "chars" };
        if (!LOCAL_RE.test(local)) return { ok: false, value: v, code: "chars" };
        if (local.charAt(0) === "." || local.charAt(local.length - 1) === "." || local.indexOf("..") !== -1) {
            return { ok: false, value: v, code: "invalid" };
        }
        if (!/^[a-z0-9.-]+$/.test(domain)) return { ok: false, value: v, code: "chars" };
        var labels = domain.split(".");
        if (labels.length < 2) return { ok: false, value: v, code: "invalid" };
        for (var i = 0; i < labels.length; i++) {
            if (labels[i].length < 1 || labels[i].length > 63 || !LABEL_RE.test(labels[i])) {
                return { ok: false, value: v, code: "invalid" };
            }
        }
        var tld = labels[labels.length - 1];
        if (!TLD_RE.test(tld)) return { ok: false, value: v, code: "invalid" };
        if (hardTldSet[tld]) {
            return { ok: false, value: v, code: "tld", suggestion: local + "@" + labels.slice(0, -1).concat(data.tldTypoSuggestion).join(".") };
        }
        if (reservedSet[domain] || domainMatches(reservedSet, domain)) return { ok: false, value: v, code: "blocked" };
        for (var s = 0; s < data.reservedSuffixes.length; s++) {
            var suf = String(data.reservedSuffixes[s]).toLowerCase();
            if (domain.length > suf.length && domain.slice(-suf.length) === suf) return { ok: false, value: v, code: "blocked" };
        }
        if (disposableSet[domain] || domainMatches(disposableSet, domain)) return { ok: false, value: v, code: "blocked" };
        var sug = suggestDomain(domain);
        var out = { ok: true, value: v, code: "" };
        if (sug) out.suggestion = local + "@" + sug;
        return out;
    }

    /* ---------- Name ---------- */
    function validateName(raw) {
        var v = normalizeText(raw).replace(/\s+/g, " ");
        if (v === "") return { ok: false, value: "", code: "required" };
        var len = Array.from(v).length;
        if (len < 2 || len > 100) return { ok: false, value: v, code: "invalid" };
        if (!/^[\p{L}\p{M}][\p{L}\p{M} .'’-]*$/u.test(v)) return { ok: false, value: v, code: "invalid" };
        return { ok: true, value: v, code: "" };
    }

    /* ---------- Phone (browser; needs an intl-tel-input instance) ---------- */
    var BLOCKED_PHONE_TYPES = [3, 4, 5, 7, 8, 9, 10];

    function cleanPhoneInput(raw) {
        var s = normalizeText(raw).replace(/[^0-9+\s().\-]/g, "");
        s = s.replace(/(?!^)\+/g, "");
        return s.slice(0, 20);
    }

    /**
     * Returns {ok, code, value, iso2, dialCode}. codes: required, country, invalid, short, long, type, not_ready.
     */
    function validatePhone(iti, rawValue) {
        var raw = cleanPhoneInput(rawValue);
        if (raw === "") return { ok: false, code: "required" };
        if (!iti || typeof window === "undefined" || !window.intlTelInputUtils) return { ok: false, code: "not_ready" };
        var country = iti.getSelectedCountryData() || {};
        if (!country.iso2 && raw.charAt(0) !== "+") return { ok: false, code: "country" };
        // Use the utils functions directly: in intl-tel-input 19.5.x the instance method iti.isValidNumber() is
        // a lenient length check (it accepts +1 111 555 2671); utils.isValidNumber() is the strict
        // libphonenumber check against the country's real numbering plan.
        var utils = window.intlTelInputUtils;
        var number = iti.getNumber();
        var iso2 = country.iso2 || "";
        if (!utils.isValidNumber(number, iso2)) {
            var err = utils.getValidationError(number, iso2);
            var code = "invalid";
            if (err === 1) code = !country.iso2 ? "country" : "invalid";
            else if (err === 2) code = "short";
            else if (err === 3) code = "long";
            return { ok: false, code: code };
        }
        var type = utils.getNumberType(number, iso2);
        if (BLOCKED_PHONE_TYPES.indexOf(type) !== -1) return { ok: false, code: "type" };
        return {
            ok: true, code: "", value: number,
            iso2: iso2, dialCode: country.dialCode ? "+" + country.dialCode : ""
        };
    }

    /* ---------- Messages ---------- */
    var M = {
        English: ["Please enter your email address.", "Email address cannot contain spaces.", "Email address contains invalid characters.", "Please enter a valid email address (for example name@example.com).", "Please use a real, permanent email address.", "Please check the end of your email address (for example .com).", "Did you mean {s}?", "Use {s}", "Please enter your name.", "Please enter a valid name (letters only, at least 2 characters).", "Please enter your phone number.", "Please select your country.", "Please enter a valid phone number for the selected country.", "This phone number is too short.", "This phone number is too long.", "Please enter a mobile or landline number.", "Still loading, please try again in a moment."],
        German: ["Bitte geben Sie Ihre E-Mail-Adresse ein.", "Die E-Mail-Adresse darf keine Leerzeichen enthalten.", "Die E-Mail-Adresse enthält ungültige Zeichen.", "Bitte geben Sie eine gültige E-Mail-Adresse ein (z. B. name@beispiel.de).", "Bitte verwenden Sie eine echte, dauerhafte E-Mail-Adresse.", "Bitte prüfen Sie das Ende Ihrer E-Mail-Adresse (z. B. .de).", "Meinten Sie {s}?", "{s} verwenden", "Bitte geben Sie Ihren Namen ein.", "Bitte geben Sie einen gültigen Namen ein (nur Buchstaben, mindestens 2 Zeichen).", "Bitte geben Sie Ihre Telefonnummer ein.", "Bitte wählen Sie Ihr Land aus.", "Bitte geben Sie eine gültige Telefonnummer für das gewählte Land ein.", "Diese Telefonnummer ist zu kurz.", "Diese Telefonnummer ist zu lang.", "Bitte geben Sie eine Mobil- oder Festnetznummer ein.", "Wird noch geladen, bitte versuchen Sie es gleich noch einmal."],
        Spanish: ["Introduce tu correo electrónico.", "El correo electrónico no puede contener espacios.", "El correo electrónico contiene caracteres no válidos.", "Introduce un correo electrónico válido (por ejemplo, nombre@ejemplo.com).", "Usa un correo electrónico real y permanente.", "Revisa el final de tu correo electrónico (por ejemplo, .com).", "¿Querías decir {s}?", "Usar {s}", "Introduce tu nombre.", "Introduce un nombre válido (solo letras, mínimo 2 caracteres).", "Introduce tu número de teléfono.", "Selecciona tu país.", "Introduce un número de teléfono válido para el país seleccionado.", "Este número de teléfono es demasiado corto.", "Este número de teléfono es demasiado largo.", "Introduce un número móvil o fijo.", "Aún se está cargando, inténtalo de nuevo en un momento."],
        French: ["Veuillez saisir votre adresse e-mail.", "L'adresse e-mail ne peut pas contenir d'espaces.", "L'adresse e-mail contient des caractères non valides.", "Veuillez saisir une adresse e-mail valide (par exemple nom@exemple.com).", "Veuillez utiliser une adresse e-mail réelle et permanente.", "Vérifiez la fin de votre adresse e-mail (par exemple .com).", "Vouliez-vous dire {s} ?", "Utiliser {s}", "Veuillez saisir votre nom.", "Veuillez saisir un nom valide (lettres uniquement, au moins 2 caractères).", "Veuillez saisir votre numéro de téléphone.", "Veuillez sélectionner votre pays.", "Veuillez saisir un numéro de téléphone valide pour le pays sélectionné.", "Ce numéro de téléphone est trop court.", "Ce numéro de téléphone est trop long.", "Veuillez saisir un numéro de mobile ou de fixe.", "Chargement en cours, veuillez réessayer dans un instant."],
        Italian: ["Inserisci il tuo indirizzo email.", "L'indirizzo email non può contenere spazi.", "L'indirizzo email contiene caratteri non validi.", "Inserisci un indirizzo email valido (ad esempio nome@esempio.com).", "Usa un indirizzo email reale e permanente.", "Controlla la parte finale dell'indirizzo email (ad esempio .com).", "Intendevi {s}?", "Usa {s}", "Inserisci il tuo nome.", "Inserisci un nome valido (solo lettere, almeno 2 caratteri).", "Inserisci il tuo numero di telefono.", "Seleziona il tuo paese.", "Inserisci un numero di telefono valido per il paese selezionato.", "Questo numero di telefono è troppo corto.", "Questo numero di telefono è troppo lungo.", "Inserisci un numero di cellulare o di rete fissa.", "Caricamento in corso, riprova tra un momento."],
        Portuguese: ["Insira seu e-mail.", "O e-mail não pode conter espaços.", "O e-mail contém caracteres inválidos.", "Insira um e-mail válido (por exemplo, nome@exemplo.com).", "Use um e-mail real e permanente.", "Verifique o final do seu e-mail (por exemplo, .com).", "Você quis dizer {s}?", "Usar {s}", "Insira seu nome.", "Insira um nome válido (apenas letras, mínimo de 2 caracteres).", "Insira seu número de telefone.", "Selecione seu país.", "Insira um número de telefone válido para o país selecionado.", "Este número de telefone é muito curto.", "Este número de telefone é muito longo.", "Insira um número de celular ou fixo.", "Ainda carregando, tente novamente em instantes."],
        Polish: ["Podaj swój adres e-mail.", "Adres e-mail nie może zawierać spacji.", "Adres e-mail zawiera niedozwolone znaki.", "Podaj prawidłowy adres e-mail (na przykład imie@przyklad.pl).", "Użyj prawdziwego, stałego adresu e-mail.", "Sprawdź końcówkę adresu e-mail (na przykład .pl).", "Czy chodziło o {s}?", "Użyj {s}", "Podaj swoje imię.", "Podaj prawidłowe imię (tylko litery, co najmniej 2 znaki).", "Podaj swój numer telefonu.", "Wybierz swój kraj.", "Podaj prawidłowy numer telefonu dla wybranego kraju.", "Ten numer telefonu jest za krótki.", "Ten numer telefonu jest za długi.", "Podaj numer komórkowy lub stacjonarny.", "Trwa ładowanie, spróbuj ponownie za chwilę."],
        Japanese: ["メールアドレスを入力してください。", "メールアドレスにスペースを含めることはできません。", "メールアドレスに使用できない文字が含まれています。", "有効なメールアドレスを入力してください（例：name@example.com）。", "実在する永続的なメールアドレスをご利用ください。", "メールアドレスの末尾（例：.com）をご確認ください。", "{s} ではありませんか？", "{s} を使用", "お名前を入力してください。", "有効なお名前を入力してください（文字のみ、2文字以上）。", "電話番号を入力してください。", "国を選択してください。", "選択した国の有効な電話番号を入力してください。", "電話番号が短すぎます。", "電話番号が長すぎます。", "携帯電話または固定電話の番号を入力してください。", "読み込み中です。しばらくしてからもう一度お試しください。"],
        Korean: ["이메일 주소를 입력해 주세요.", "이메일 주소에는 공백을 사용할 수 없습니다.", "이메일 주소에 사용할 수 없는 문자가 포함되어 있습니다.", "올바른 이메일 주소를 입력해 주세요 (예: name@example.com).", "실제로 사용하는 영구 이메일 주소를 입력해 주세요.", "이메일 주소의 끝부분(예: .com)을 확인해 주세요.", "{s}을(를) 입력하려던 것인가요?", "{s} 사용", "이름을 입력해 주세요.", "올바른 이름을 입력해 주세요 (문자만, 2자 이상).", "전화번호를 입력해 주세요.", "국가를 선택해 주세요.", "선택한 국가에 맞는 올바른 전화번호를 입력해 주세요.", "전화번호가 너무 짧습니다.", "전화번호가 너무 깁니다.", "휴대폰 또는 일반 전화번호를 입력해 주세요.", "로딩 중입니다. 잠시 후 다시 시도해 주세요."]
    };
    var KEYS = ["email_required", "email_space", "email_chars", "email_invalid", "email_blocked", "email_tld", "email_suggest", "email_use", "name_required", "name_invalid", "phone_required", "phone_country", "phone_invalid", "phone_short", "phone_long", "phone_type", "not_ready"];

    function message(lang, key, sub) {
        var table = M[lang] || M.English;
        var idx = KEYS.indexOf(key);
        var text = idx === -1 ? key : (table[idx] || M.English[idx]);
        return sub != null ? text.replace("{s}", sub) : text;
    }

    function emailMessageKey(code) {
        return { required: "email_required", space: "email_space", chars: "email_chars", invalid: "email_invalid", blocked: "email_blocked", tld: "email_tld" }[code] || "email_invalid";
    }
    function phoneMessageKey(code) {
        return { required: "phone_required", country: "phone_country", invalid: "phone_invalid", short: "phone_short", long: "phone_long", type: "phone_type", not_ready: "not_ready" }[code] || "phone_invalid";
    }

    /* Detect the form language from field placeholders (the editor does not store it). */
    var PLACEHOLDERS = {
        English: ["first name", "last name", "email", "phone"], German: ["vorname", "nachname", "e-mail", "telefon"],
        Spanish: ["nombre", "apellido", "correo electrónico", "teléfono"], Japanese: ["名", "姓", "メール", "電話"],
        French: ["prénom", "nom", "e-mail", "téléphone"], Italian: ["nome", "cognome", "email", "telefono"],
        Korean: ["이름", "성", "이메일", "전화번호"], Polish: ["imię", "nazwisko", "e-mail", "telefon"],
        Portuguese: ["nome", "sobrenome", "e-mail", "telefone"]
    };
    function detectLanguage(placeholders) {
        var list = [];
        for (var i = 0; i < placeholders.length; i++) list.push(normalizeText(placeholders[i]).toLowerCase());
        var best = "English", bestScore = 0;
        for (var lang in PLACEHOLDERS) {
            var score = 0;
            for (var p = 0; p < PLACEHOLDERS[lang].length; p++) {
                if (list.indexOf(PLACEHOLDERS[lang][p]) !== -1) score++;
            }
            if (score > bestScore) { best = lang; bestScore = score; }
        }
        return best;
    }

    return {
        configure: configure, normalizeText: normalizeText, validateEmail: validateEmail, validateName: validateName,
        validatePhone: validatePhone, cleanPhoneInput: cleanPhoneInput, suggestDomain: suggestDomain,
        message: message, emailMessageKey: emailMessageKey, phoneMessageKey: phoneMessageKey,
        detectLanguage: detectLanguage, languages: Object.keys(M), messageKeys: KEYS, osaDistance: osaDistance,
        messageTable: function () {
            var out = {};
            for (var lang in M) {
                out[lang] = {};
                for (var i = 0; i < KEYS.length; i++) out[lang][KEYS[i]] = M[lang][i];
            }
            return out;
        }
    };
});
