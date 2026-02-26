const i18nDict = {
    en: {
        nav_create: "Create New Paste",
        nav_view: "View Posts",
        tagline: "Zero-knowledge pastebin. Configure expiry, view limit and end‑to‑end encryption in one place.",
        secret_warning: "⚠️ Looks like this might contain credentials. Make sure you trust the recipient.",
        lbl_title: "Title (Optional)",
        lbl_expiration: "Paste Expiration:",
        exp_never: "Never",
        exp_10m: "10 Minutes",
        exp_1h: "1 Hour",
        exp_1d: "1 Day",
        exp_1w: "1 Week",
        exp_burn: "Burn After Reading",
        lbl_exposure: "Paste Exposure:",
        exp_pub: "Public",
        exp_unlisted: "Unlisted",
        lbl_view_limit: "View Limit (Optional)",
        lbl_password: "Password (optional)",
        lbl_e2ee: "End-to-End Encryption (E2EE) - ",
        lbl_e2ee_desc: "Password will be required to decrypt. Content is never sent raw to server.",
        tab_editor: "Editor",
        tab_preview: "Preview",
        est_reading: "Est. Reading: ",
        btn_publish: "Publish",
        res_ready: "Your paste is ready!",
        btn_copy: "Copy",
        ph_title: "Enter title (e.g. My Notes)",
        ph_limit: "e.g. 5",
        ph_password: "Enter password",
        
        // View page
        btn_qr: "QR Code",
        btn_zen: "Zen Mode",
        btn_copy_content: "Copy Content",
        btn_edit: "Edit",
        btn_clone: "Clone",
        btn_delete: "Delete Post",
        btn_unlock: "Unlock",
        btn_decrypt: "Decrypt Content",
        btn_exit_zen: "Exit Zen Mode",
        btn_copy_link: "Copy Link",
        btn_copy_raw: "Copy Raw",
        btn_prev: "Previous",
        btn_next: "Next",
        lbl_no_content: "No content available."
    },
    ko: {
        nav_create: "새 게시글 작성",
        nav_view: "게시글 목록",
        tagline: "영지식(Zero-knowledge) 페이스트빈. 만료일, 조회수 제한, 종단간 암호화(E2EE)를 한 번에 설정하세요.",
        secret_warning: "⚠️ 중요 정보(자격 증명)가 포함될 수 있습니다. 수신자를 신뢰할 수 있는지 확인하세요.",
        lbl_title: "제목 (선택 사항)",
        lbl_expiration: "게시글 만료일:",
        exp_never: "만료 없음",
        exp_10m: "10분",
        exp_1h: "1시간",
        exp_1d: "1일",
        exp_1w: "1주일",
        exp_burn: "읽은 후 폭파",
        lbl_exposure: "공개 범위:",
        exp_pub: "공개",
        exp_unlisted: "일부 공개",
        lbl_view_limit: "조회수 제한 (선택 사항)",
        lbl_password: "비밀번호 (선택 사항)",
        lbl_e2ee: "종단간 암호화 (E2EE) - ",
        lbl_e2ee_desc: "복호화하려면 비밀번호가 필요합니다. 서버에 원본 데이터가 전송되지 않습니다.",
        tab_editor: "에디터",
        tab_preview: "미리보기",
        est_reading: "예상 읽기 시간: ",
        btn_publish: "발행하기",
        res_ready: "게시글이 준비되었습니다!",
        btn_copy: "복사",
        ph_title: "제목을 입력하세요 (예: 나의 메모)",
        ph_limit: "예: 5",
        ph_password: "비밀번호 입력",

        btn_qr: "QR 코드",
        btn_zen: "가독성 모드",
        btn_copy_content: "본문 복사",
        btn_edit: "수정",
        btn_clone: "복제",
        btn_delete: "게시글 삭제",
        btn_unlock: "잠금 해제",
        btn_decrypt: "복호화",
        btn_exit_zen: "가독성 모드 종료",
        btn_copy_link: "링크 복사",
        btn_copy_raw: "원본 복사",
        btn_prev: "이전",
        btn_next: "다음",
        lbl_no_content: "콘텐츠가 없습니다."
    }
};

let currentLang = localStorage.getItem('kitepad_lang') || navigator.language.slice(0, 2) || 'en';
if (!i18nDict[currentLang]) currentLang = 'en';

function setLanguage(lang) {
    if (!i18nDict[lang]) return;
    currentLang = lang;
    localStorage.setItem('kitepad_lang', lang);
    applyTranslations();
    document.documentElement.lang = lang;
}

function applyTranslations() {
    const texts = i18nDict[currentLang];
    
    // Selectors mapped to translation keys
    const map = {
        '.nav-links a[href="/"]': 'nav_create',
        '.nav-links a[href="/view"]': 'nav_view',
        '.tagline': 'tagline',
        '#secret-banner span': 'secret_warning',
        'label[for="title"]': 'lbl_title',
        'label[for="expiration"]': 'lbl_expiration',
        'option[value="never"]': 'exp_never',
        'option[value="10m"]': 'exp_10m',
        'option[value="1h"]': 'exp_1h',
        'option[value="1d"]': 'exp_1d',
        'option[value="1w"]': 'exp_1w',
        'option[value="burn"]': 'exp_burn',
        'label[for="exposure"]': 'lbl_exposure',
        'option[value="public"]': 'exp_pub',
        'option[value="unlisted"]': 'exp_unlisted',
        'label[for="view_limit"]': 'lbl_view_limit',
        'label[for="password"]': 'lbl_password',
        '#editor-tab': 'tab_editor',
        '#preview-tab': 'tab_preview',
        '.publish-button': 'btn_publish',
        '#result-container p:first-child': 'res_ready',
        '.zen-exit-btn': 'btn_exit_zen'
    };

    for (const [selector, key] of Object.entries(map)) {
        const el = document.querySelector(selector);
        if (el && texts[key]) el.textContent = texts[key];
    }

    // Placeholders
    const inputs = {
        '#title': 'ph_title',
        '#view_limit': 'ph_limit',
        '#password': 'ph_password'
    };
    for (const [selector, key] of Object.entries(inputs)) {
        const el = document.querySelector(selector);
        if (el && texts[key]) el.placeholder = texts[key];
    }

    // Special handling for E2EE label with inner HTML
    const e2eeCheckbox = document.getElementById('is_encrypted');
    if (e2eeCheckbox && e2eeCheckbox.parentNode) {
        const span = e2eeCheckbox.parentNode.querySelector('span');
        if (span) span.textContent = texts['lbl_e2ee_desc'];
        // The text node after checkbox
        Array.from(e2eeCheckbox.parentNode.childNodes).forEach(node => {
            if (node.nodeType === 3 && node.textContent.trim().length > 0) {
                node.textContent = " " + texts['lbl_e2ee'];
            }
        });
    }

    // Replace all copy/clone/qr buttons by looping and checking textContent
    document.querySelectorAll('.copy-btn').forEach(btn => {
        const text = btn.textContent.trim();
        if (text === "Copy" || text === "복사") btn.textContent = texts['btn_copy'];
        if (text === "QR Code" || text === "QR 코드") btn.textContent = texts['btn_qr'];
        if (text === "Zen Mode" || text === "가독성 모드") btn.textContent = texts['btn_zen'];
        if (text === "Copy Content" || text === "본문 복사") btn.textContent = texts['btn_copy_content'];
        if (text === "Edit" || text === "수정") btn.textContent = texts['btn_edit'];
        if (text === "Clone" || text === "복제") btn.textContent = texts['btn_clone'];
        if (text === "Delete Post" || text === "게시글 삭제") btn.textContent = texts['btn_delete'];
        if (text === "Unlock" || text === "잠금 해제") btn.textContent = texts['btn_unlock'];
        if (text === "Decrypt Content" || text === "복호화") btn.textContent = texts['btn_decrypt'];
        if (text === "Copy Link" || text === "링크 복사") btn.textContent = texts['btn_copy_link'];
        if (text === "Copy Raw" || text === "원본 복사") btn.textContent = texts['btn_copy_raw'];
        if (text === "Previous" || text === "이전") btn.textContent = texts['btn_prev'];
        if (text === "Next" || text === "다음") btn.textContent = texts['btn_next'];
    });

}

function toggleLang() {
    setLanguage(currentLang === 'en' ? 'ko' : 'en');
}

window.addEventListener('DOMContentLoaded', () => {
    applyTranslations();
    
    // Insert language toggle button into nav
    const navLinks = document.querySelector('.nav-links');
    if (navLinks) {
        // Avoid duplicate
        if (!document.querySelector('.lang-toggle')) {
            const langToggle = document.createElement('a');
            langToggle.href = '#';
            langToggle.className = 'nav-link lang-toggle';
            langToggle.onclick = (e) => { e.preventDefault(); toggleLang(); };
            langToggle.textContent = '🇰🇷/🇺🇸 EN/KO';
            langToggle.style.float = 'right';
            navLinks.appendChild(langToggle);
        }
    }
});
