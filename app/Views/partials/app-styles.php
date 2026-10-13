<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
?>
/* Partes de apresentação do shell MVC; a paleta base vem de monana-theme.css. */
.skip-link {
    position: absolute;
    top: -60px;
    left: 12px;
    z-index: 1200;
    padding: 10px 14px;
    border-radius: 8px;
    background: var(--brown-900);
    color: #fff;
    font-weight: 600;
}
.skip-link:focus { top: 12px; }
.topbar .left .area-label { display: inline-block; overflow: hidden; text-overflow: ellipsis; }
.topbar .right .who { cursor: default; }
.platform-label, .tenant-tag {
    display: inline-flex;
    align-items: center;
    max-width: 280px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-left: 9px;
    padding: 4px 9px;
    border-radius: 30px;
    background: var(--gold-50);
    color: var(--gold-700);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.sb-item.is-disabled {
    color: var(--brown-400);
    cursor: not-allowed;
    opacity: .82;
}
.sb-item.is-disabled .ic { color: var(--brown-300); }
.nav-phase-badge {
    margin-left: auto;
    padding: 3px 6px;
    border: 1px solid var(--line);
    border-radius: 20px;
    color: var(--brown-400);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: .02em;
}
.sb-note {
    margin: 7px 8px 10px;
    padding: 10px 11px;
    border: 1px solid var(--gold-100);
    border-radius: 9px;
    background: var(--gold-50);
    color: var(--brown-500);
    font-size: 11px;
    line-height: 1.5;
}
.sb-nav form { margin: 0; }
.sb-nav form .sb-item {
    width: 100%;
    border: 0;
    background: transparent;
    font-family: var(--font-ui);
    text-align: left;
}
.sb-nav form .sb-item:hover { background: var(--gold-50); }
.sb-nav form .sb-item.logout-btn:hover { background: var(--danger-bg); }
.content { max-width: 1600px; }
body.area-igreja .content { max-width: 1200px; }
body.area-superadmin .content { max-width: 1600px; }

/* Componentes das páginas administrativas já migradas na Fase 3. */
.admin-table-wrap { overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.admin-table th {
    padding: 11px 9px;
    border-bottom: 1px solid var(--line);
    color: var(--brown-500);
    font-size: 10px;
    letter-spacing: .08em;
    text-align: left;
    text-transform: uppercase;
}
.admin-table td {
    padding: 12px 9px;
    border-bottom: 1px solid var(--line-2);
    color: var(--brown-700);
    vertical-align: top;
}
.admin-table tr:last-child td { border-bottom: 0; }
.admin-table a { color: var(--gold-700); font-weight: 600; text-decoration: none; }
.admin-table a:hover { text-decoration: underline; }
.admin-actions { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.admin-actions form { display: inline; }
.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 8px 12px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #fff;
    color: var(--brown-700);
    font: 600 12px var(--font-ui);
    text-decoration: none;
    cursor: pointer;
}
.admin-btn:hover { border-color: var(--gold-300); background: var(--gold-50); }
.admin-btn.primary { border: 0; background: var(--grad-gold); color: #fff; }
.admin-btn.danger { border-color: #f0cfc7; color: #a43122; }
.admin-btn.small { padding: 6px 9px; font-size: 11px; }
.admin-form { max-width: 720px; }
.admin-form .field-group { margin-bottom: 16px; }
.admin-form label {
    display: block;
    margin-bottom: 7px;
    color: var(--brown-800);
    font-size: 12px;
    font-weight: 600;
}
.admin-form input, .admin-form select, .admin-form textarea {
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #fff;
    color: var(--brown-900);
    font-size: 13px;
}
.admin-form textarea { min-height: 88px; resize: vertical; }
.admin-form .hint { margin-top: 5px; color: var(--brown-500); font-size: 11.5px; line-height: 1.5; }
.admin-form .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; }
.admin-form .form-grid .field-group { margin-bottom: 2px; }
.status-pill {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 20px;
    background: var(--gold-50);
    color: var(--gold-700);
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    white-space: nowrap;
}
.status-pill.ok { background: #eaf3ec; color: #3f7d4e; }
.status-pill.bad { background: #fbebe7; color: #b5412f; }
.status-pill.pending { background: #fbf3df; color: #8a6317; }
.empty-note { padding: 18px; color: var(--brown-500); font-size: 13px; }
.panel-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
.panel-head h3 { margin: 0; }
.breadcrumb { margin-bottom: 18px; color: var(--brown-500); font-size: 12px; }
.breadcrumb a { color: var(--gold-700); text-decoration: none; }
.danger-zone { padding: 16px; border: 1px solid #f0cfc7; border-radius: 10px; background: #fffafa; }
.inline-notice { margin-top: 12px; }
.phase-notice {
    padding: 20px;
    border: 1px solid var(--gold-200);
    border-radius: var(--radius-lg);
    background: var(--gold-50);
    color: var(--brown-700);
    line-height: 1.7;
}
.phase-notice strong { color: var(--brown-900); }
.dashboard-actions { margin-top: 20px; }
@media (max-width: 768px) {
    .admin-form .form-grid { grid-template-columns: 1fr; }
    .admin-table { min-width: 700px; }
    .platform-label, .tenant-tag { display: none; }
}
