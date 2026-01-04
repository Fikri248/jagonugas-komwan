<?php
// admin-navbar.php
$currentPage = basename($_SERVER['PHP_SELF']);
$BASE = defined('BASE_PATH') ? constant('BASE_PATH') : '';

// Get admin name from session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminName = $_SESSION['name'] ?? 'Admin';
$adminInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($adminName, 0, 1));
?>
<style>
/* Admin Navbar Styles */
.admin-navbar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.admin-navbar .nav-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.admin-navbar .nav-left {
    display: flex;
    align-items: center;
    gap: 40px;
}

.admin-navbar .logo {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s;
}

.admin-navbar .logo:hover {
    transform: scale(1.05);
}

.admin-navbar .logo i {
    font-size: 1.8rem;
}

.admin-navbar .nav-links {
    display: flex;
    gap: 8px;
    align-items: center;
}

.admin-navbar .nav-link {
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    font-weight: 600;
    padding: 10px 16px;
    border-radius: 8px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
}

.admin-navbar .nav-link:hover {
    background: rgba(255,255,255,0.2);
    color: white;
    transform: translateY(-2px);
}

.admin-navbar .nav-link.active {
    background: rgba(255,255,255,0.3);
    color: white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.admin-navbar .nav-link i {
    font-size: 1.1rem;
}

.admin-navbar .nav-right {
    display: flex;
    align-items: center;
    gap: 16px;
}

.admin-navbar .user-menu {
    position: relative;
}

.admin-navbar .user-trigger {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    background: rgba(255,255,255,0.15);
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid rgba(255,255,255,0.2);
}

.admin-navbar .user-trigger:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
}

.admin-navbar .user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: white;
    color: #667eea;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
}

.admin-navbar .user-info {
    color: white;
}

.admin-navbar .user-name {
    font-weight: 600;
    font-size: 0.95rem;
    display: block;
    line-height: 1.2;
}

.admin-navbar .user-role {
    font-size: 0.75rem;
    opacity: 0.9;
}

.admin-navbar .user-trigger i.bi-chevron-down {
    color: white;
    font-size: 0.9rem;
    transition: transform 0.3s;
}

.admin-navbar .user-menu.active .user-trigger i.bi-chevron-down {
    transform: rotate(180deg);
}

.admin-navbar .user-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    min-width: 220px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s;
    overflow: hidden;
}

.admin-navbar .user-menu.active .user-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.admin-navbar .user-dropdown::before {
    content: '';
    position: absolute;
    top: -6px;
    right: 24px;
    width: 12px;
    height: 12px;
    background: white;
    transform: rotate(45deg);
}

.admin-navbar .dropdown-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    text-decoration: none;
    color: #475569;
    font-weight: 600;
    transition: all 0.3s;
    border-bottom: 1px solid #f1f5f9;
}

.admin-navbar .dropdown-link:last-child {
    border-bottom: none;
}

.admin-navbar .dropdown-link:hover {
    background: #f8fafc;
    color: #667eea;
}

.admin-navbar .dropdown-link i {
    font-size: 1.2rem;
    width: 24px;
}

.admin-navbar .dropdown-link.logout {
    color: #dc2626;
}

.admin-navbar .dropdown-link.logout:hover {
    background: #fef2f2;
    color: #dc2626;
}

/* Mobile Menu Toggle */
.admin-navbar .mobile-toggle {
    display: none;
    background: rgba(255,255,255,0.2);
    border: 2px solid rgba(255,255,255,0.3);
    color: white;
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1.5rem;
}

/* Mobile Responsive */
@media (max-width: 1024px) {
    .admin-navbar .nav-links {
        position: fixed;
        top: 72px;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        flex-direction: column;
        gap: 0;
        padding: 16px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-20px);
        transition: all 0.3s;
    }
    
    .admin-navbar .nav-links.active {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .admin-navbar .nav-link {
        width: 100%;
        justify-content: flex-start;
        padding: 14px 20px;
    }
    
    .admin-navbar .mobile-toggle {
        display: block;
    }
    
    .admin-navbar .user-info {
        display: none;
    }
}

@media (max-width: 768px) {
    .admin-navbar .nav-container {
        padding: 12px 16px;
    }
    
    .admin-navbar .logo {
        font-size: 1.2rem;
    }
    
    .admin-navbar .logo i {
        font-size: 1.5rem;
    }
}
</style>

<nav class="admin-navbar">
    <div class="nav-container">
        <div class="nav-left">
            <a href="<?php echo htmlspecialchars($BASE . '/admin-dashboard.php'); ?>" class="logo">
                <i class="bi bi-shield-check"></i>
                <span>Admin Panel</span>
            </a>
            
            <div class="nav-links" id="navLinks">
                <a href="<?php echo htmlspecialchars($BASE . '/admin-dashboard.php'); ?>" 
                   class="nav-link <?php echo $currentPage === 'admin-dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="<?php echo htmlspecialchars($BASE . '/admin-users.php'); ?>" 
                   class="nav-link <?php echo $currentPage === 'admin-users.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people-fill"></i> Users
                </a>
                <a href="<?php echo htmlspecialchars($BASE . '/admin-mentors.php'); ?>" 
                   class="nav-link <?php echo $currentPage === 'admin-mentors.php' ? 'active' : ''; ?>">
                    <i class="bi bi-mortarboard-fill"></i> Mentors
                </a>
                <a href="<?php echo htmlspecialchars($BASE . '/admin-transactions.php'); ?>" 
                   class="nav-link <?php echo $currentPage === 'admin-transactions.php' ? 'active' : ''; ?>">
                    <i class="bi bi-credit-card-fill"></i> Transaksi
                </a>
                <a href="<?php echo htmlspecialchars($BASE . '/admin-settings.php'); ?>" 
                   class="nav-link <?php echo $currentPage === 'admin-settings.php' ? 'active' : ''; ?>">
                    <i class="bi bi-gear-fill"></i> Settings
                </a>
            </div>
        </div>
        
        <div class="nav-right">
            <button class="mobile-toggle" id="mobileToggle">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="user-menu" id="userMenu">
                <div class="user-trigger">
                    <div class="user-avatar"><?php echo htmlspecialchars($adminInitial); ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($adminName); ?></span>
                        <span class="user-role">Administrator</span>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                
                <div class="user-dropdown">
                    <a href="<?php echo htmlspecialchars($BASE . '/admin-profile.php'); ?>" class="dropdown-link">
                        <i class="bi bi-person-circle"></i>
                        <span>Profile</span>
                    </a>
                    <a href="<?php echo htmlspecialchars($BASE . '/admin-settings.php'); ?>" class="dropdown-link">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                    <a href="<?php echo htmlspecialchars($BASE . '/logout.php'); ?>" class="dropdown-link logout">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
// User menu dropdown toggle
document.addEventListener('DOMContentLoaded', function() {
    const userMenu = document.getElementById('userMenu');
    const mobileToggle = document.getElementById('mobileToggle');
    const navLinks = document.getElementById('navLinks');
    
    // Desktop user menu
    if (userMenu) {
        userMenu.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenu.contains(e.target)) {
                userMenu.classList.remove('active');
            }
        });
    }
    
    // Mobile menu toggle
    if (mobileToggle && navLinks) {
        mobileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            navLinks.classList.toggle('active');
            
            // Change icon
            const icon = this.querySelector('i');
            if (navLinks.classList.contains('active')) {
                icon.className = 'bi bi-x-lg';
            } else {
                icon.className = 'bi bi-list';
            }
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!navLinks.contains(e.target) && !mobileToggle.contains(e.target)) {
                navLinks.classList.remove('active');
                const icon = mobileToggle.querySelector('i');
                icon.className = 'bi bi-list';
            }
        });
        
        // Close mobile menu when clicking a link
        const links = navLinks.querySelectorAll('.nav-link');
        links.forEach(link => {
            link.addEventListener('click', function() {
                navLinks.classList.remove('active');
                const icon = mobileToggle.querySelector('i');
                icon.className = 'bi bi-list';
            });
        });
    }
});
</script>
