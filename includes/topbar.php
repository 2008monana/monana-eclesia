<?php
// includes/topbar.php
// Este arquivo contém a barra superior com botão hambúrguer, notificações e perfil do usuário
?>
<div class="topbar">
    <div class="left">
        <button class="burger" id="menuToggle" aria-label="Abrir menu">
            <i class="fas fa-bars"></i>
        </button>
        <span>Sistema de Gestão de Contribuições</span>
    </div>
    <div class="right">
        <!-- Notificações
        <div class="bell" id="notificationToggle">
            <i class="fas fa-bell"></i>
            <span class="badge">3</span>
            <!-- Dropdown de Notificações 
            <div class="notifications-dropdown" id="notificationsDropdown">
                <div class="header">
                    <span>Notificações</span>
                    <span class="mark-all">Marcar todas como lidas</span>
                </div>
                <div class="notification-item unread">
                    <div class="icon" style="background:#ec4899;">
                        <i class="fas fa-female"></i>
                    </div>
                    <div class="content">
                        <div class="title">Nova mamã registada</div>
                        <div class="desc">Maria Joaquina foi registada como membro na categoria Mamã</div>
                        <div class="time">há 2 horas</div>
                    </div>
                </div>
                <div class="notification-item unread">
                    <div class="icon" style="background:#10b981;">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="content">
                        <div class="title">Pagamento registado</div>
                        <div class="desc">João Paulo realizou o pagamento da cota de Maio/2026</div>
                        <div class="time">há 5 horas</div>
                    </div>
                </div>
                <div class="notification-item">
                    <div class="icon" style="background:#f59e0b;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="content">
                        <div class="title">Membros em dívida</div>
                        <div class="desc">12 membros ainda não pagaram a cota deste mês</div>
                        <div class="time">há 1 dia</div>
                    </div>
                </div>
                <div class="notification-item">
                    <div class="icon" style="background:#8b5cf6;">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="content">
                        <div class="title">Relatório gerado</div>
                        <div class="desc">Relatório mensal de Abril/2026 foi gerado com sucesso</div>
                        <div class="time">há 3 dias</div>
                    </div>
                </div>
            </div>
        </div>-->

        <!-- Usuário -->
        <div class="user-dropdown-wrapper" id="userDropdownWrapper">
            <div class="who" id="userDropdownToggle">
                <div class="av2"><?= strtoupper(substr($_SESSION['user_nome'], 0, 2)) ?></div>
                <div class="user-info">
                    <div class="t1"><?= htmlspecialchars($_SESSION['user_nome']) ?></div>
                    <div class="t2"><?= htmlspecialchars($_SESSION['user_perfil']) ?></div>
                </div>
                <i class="fas fa-chevron-down chevron-down"></i>
            </div>

            <!-- Dropdown do Usuário -->
            <div class="user-dropdown" id="userDropdown">
                <div class="user-header">
                    <div class="av3"><?= strtoupper(substr($_SESSION['user_nome'], 0, 2)) ?></div>
                    <div class="info">
                        <div class="name"><?= htmlspecialchars($_SESSION['user_nome']) ?></div>
                        <div class="email"><?= htmlspecialchars($_SESSION['user_email']) ?></div>
                    </div>
                </div>
                <a href="<?= url('modules/profile/index.php') ?>" class="menu-item">
                    <i class="fas fa-user"></i> Meu Perfil
                </a>
                <div class="divider"></div>
                <a href="<?= url('modules/auth/logout.php') ?>" class="menu-item danger logout-trigger" id="logoutBtn">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
MODAL DE CONFIRMAÇÃO DE LOGOUT
========================================== -->
<div id="logoutModal" class="confirm-overlay" style="display:none;">
    <div class="confirm-box">
        <div class="ico"><i class="fas fa-sign-out-alt"></i></div>
        <h3>Confirmar Saída</h3>
        <p>Tem certeza que deseja sair do sistema?</p>
        <div class="actions">
            <button id="confirmLogoutBtn" class="btn-danger"><i class="fas fa-check"></i> Sim, Sair</button>
            <button id="cancelLogoutBtn" class="btn-ghost"><i class="fas fa-times"></i> Cancelar</button>
        </div>
    </div>
</div>

<script>
// ============================================
// INICIALIZAR TODOS OS SCRIPTS QUANDO O DOM ESTIVER PRONTO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // LOGOUT COM MODAL DE CONFIRMAÇÃO
    // ============================================
    const logoutTriggers = document.querySelectorAll('.logout-trigger');
    const logoutModal = document.getElementById('logoutModal');
    const confirmLogoutBtn = document.getElementById('confirmLogoutBtn');
    const cancelLogoutBtn = document.getElementById('cancelLogoutBtn');

    if (logoutTriggers.length && logoutModal) {
        // Abrir modal
        logoutTriggers.forEach(function(trigger) {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                logoutModal.style.display = 'flex';
            });
        });

        // Fechar modal - Cancelar
        if (cancelLogoutBtn) {
            cancelLogoutBtn.addEventListener('click', function() {
                logoutModal.style.display = 'none';
                // Resetar texto
                const title = logoutModal.querySelector('h3');
                const msg = logoutModal.querySelector('p');
                if (title) title.textContent = 'Confirmar Saída';
                if (msg) msg.textContent = 'Tem certeza que deseja sair do sistema?';
                const btns = logoutModal.querySelectorAll('button');
                btns.forEach(btn => btn.disabled = false);
            });
        }

        // Fechar modal - clicar fora
        logoutModal.addEventListener('click', function(e) {
            if (e.target === this) {
                logoutModal.style.display = 'none';
                const title = logoutModal.querySelector('h3');
                const msg = logoutModal.querySelector('p');
                if (title) title.textContent = 'Confirmar Saída';
                if (msg) msg.textContent = 'Tem certeza que deseja sair do sistema?';
                const btns = logoutModal.querySelectorAll('button');
                btns.forEach(btn => btn.disabled = false);
            }
        });

        // Confirmar logout
        if (confirmLogoutBtn) {
            confirmLogoutBtn.addEventListener('click', function() {
                const modal = logoutModal;
                const title = modal.querySelector('h3');
                const msg = modal.querySelector('p');
                const btns = modal.querySelectorAll('button');
                
                // Mostrar feedback
                if (title) title.textContent = 'A processar...';
                if (msg) msg.textContent = 'A encerrar sessão...';
                btns.forEach(btn => btn.disabled = true);
                
                // Enviar requisição AJAX
                fetch('<?= url('modules/auth/logout.php') ?>', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.redirect;
                    } else {
                        alert('Erro ao fazer logout. Tente novamente.');
                        modal.style.display = 'none';
                        if (title) title.textContent = 'Confirmar Saída';
                        if (msg) msg.textContent = 'Tem certeza que deseja sair do sistema?';
                        btns.forEach(btn => btn.disabled = false);
                    }
                })
                .catch(function(error) {
                    alert('Erro ao fazer logout. Tente novamente.');
                    modal.style.display = 'none';
                    if (title) title.textContent = 'Confirmar Saída';
                    if (msg) msg.textContent = 'Tem certeza que deseja sair do sistema?';
                    btns.forEach(btn => btn.disabled = false);
                });
            });
        }

        // Fechar com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && logoutModal.style.display === 'flex') {
                logoutModal.style.display = 'none';
                const title = logoutModal.querySelector('h3');
                const msg = logoutModal.querySelector('p');
                if (title) title.textContent = 'Confirmar Saída';
                if (msg) msg.textContent = 'Tem certeza que deseja sair do sistema?';
                const btns = logoutModal.querySelectorAll('button');
                btns.forEach(btn => btn.disabled = false);
            }
        });
    }

    // ============================================
    // MENU MOBILE - ABRIR/FECHAR SIDEBAR
    // ============================================
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });

        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        // Fechar sidebar ao clicar em links (mobile)
        document.querySelectorAll('.sb-item, .sb-sub div').forEach(function(item) {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('open');
                    if (overlay) overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
    }

    // ============================================
    // DROPDOWN DO USUÁRIO
    // ============================================
    const userToggle = document.getElementById('userDropdownToggle');
    const userDropdown = document.getElementById('userDropdown');

    if (userToggle && userDropdown) {
        userToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
            this.classList.toggle('active');
            
            // Fechar notificações
            const notifDropdown = document.getElementById('notificationsDropdown');
            if (notifDropdown) notifDropdown.classList.remove('active');
        });
    }

    // ============================================
    // DROPDOWN DE NOTIFICAÇÕES
    // ============================================
    const notifToggle = document.getElementById('notificationToggle');
    const notifDropdown = document.getElementById('notificationsDropdown');

    if (notifToggle && notifDropdown) {
        notifToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('active');
            
            // Fechar dropdown do usuário
            if (userDropdown) {
                userDropdown.classList.remove('active');
                if (userToggle) userToggle.classList.remove('active');
            }
        });
    }

    // ============================================
    // FECHAR DROPDOWNS AO CLICAR FORA
    // ============================================
    document.addEventListener('click', function(e) {
        // Fechar dropdown do usuário
        if (userToggle && userDropdown) {
            if (!userToggle.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('active');
                userToggle.classList.remove('active');
            }
        }
        // Fechar dropdown de notificações
        if (notifToggle && notifDropdown) {
            if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) {
                notifDropdown.classList.remove('active');
            }
        }
    });

    // ============================================
    // MARCAR NOTIFICAÇÕES COMO LIDAS
    // ============================================
    const markAllBtn = document.querySelector('.mark-all');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            document.querySelectorAll('.notification-item.unread').forEach(function(item) {
                item.classList.remove('unread');
            });
            const badge = document.querySelector('.bell .badge');
            if (badge) badge.textContent = '0';
            markAllBtn.textContent = 'Todas lidas';
        });
    }

}); // Fim do DOMContentLoaded
</script>