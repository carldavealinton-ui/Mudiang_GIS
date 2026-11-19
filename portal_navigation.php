<?php
function portalEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function renderPortalNavigation(): void {
    $role=$_SESSION['role']??'resident';
    $inAdmin=strpos($_SERVER['SCRIPT_NAME']??'','/admin/')!==false;
    $prefix=$inAdmin?'../':'';$adminPrefix=$inAdmin?'':'admin/';
    $page=basename($_SERVER['SCRIPT_NAME']??'');
    if($role==='admin') {
        $groups=['Community operations'=>[
            'dashboard.php'=>['fa-th-large','Dashboard'],'operations.php'=>['fa-chart-line','Operations'],
            'messages.php'=>['fa-comments','Messages'],'residents.php'=>['fa-users','Residents'],
            'activity.php'=>['fa-history','Activity Logs'],'annual_report.php'=>['fa-chart-bar','Reports'],
        ],'Administration'=>[
            'announcements.php'=>['fa-bullhorn','Announcements'],
            'staff.php'=>['fa-user-shield','Staff Accounts'],'account_access.php'=>['fa-key','Account access'],
        ]];
    } elseif($role==='staff') {
        $groups=['My workspace'=>['staff_dashboard.php'=>['fa-briefcase','My workspace'],'messages.php'=>['fa-comments','My messages'],'activity.php'=>['fa-history','My activity']]];
    } else {
        $groups=['Resident services'=>['resident_dashboard.php'=>['fa-th-large','My Dashboard'],'submit_complaint_form.php'=>['fa-plus-circle','Submit Complaint'],'announcements.php'=>['fa-bullhorn','Announcements'],'messages.php'=>['fa-comments','Messages'],'profile.php'=>['fa-user','My profile']]];
    }
    $name=$_SESSION['full_name']??$_SESSION['username']??'Account';
    $logoPath='assets/images/barangay-mudiang-logo.png';
    $brandLogo=is_file(__DIR__.'/'.$logoPath)
        ? '<img src="'.portalEscape($prefix.$logoPath).'" alt="Barangay Mudiang seal" width="88" height="88" style="display:block;width:88px;height:88px;object-fit:contain;margin-bottom:12px">'
        : '<div class="brand-icon"><i class="fas fa-map-marked-alt" aria-hidden="true"></i></div>';
    echo '<aside class="sidebar"><div class="sidebar-brand">'.$brandLogo.'<h2>Barangay Mudiang</h2><p>Community services portal</p></div><nav class="sidebar-nav" aria-label="'.($role==='resident'?'Resident':'Administration').' navigation">';
    foreach($groups as $label=>$items) {
        echo '<div class="nav-label">'.portalEscape($label).'</div>';
        foreach($items as $url=>$item) {
            $active=$page===$url || ($page==='complaint_conversation.php'&&$url==='resident_dashboard.php') || ($page==='edit_complaint.php'&&$url===($role==='staff'?'staff_dashboard.php':'dashboard.php'));
            $href=($role==='resident'?$prefix:$adminPrefix).$url;
            echo '<a class="nav-item'.($active?' active':'').'" href="'.portalEscape($href).'"'.($active?' aria-current="page"':'').'><i class="fas '.$item[0].'" aria-hidden="true"></i> '.portalEscape($item[1]).'</a>';
        }
    }
    echo '<div class="nav-label">Account</div><a class="nav-item'.($page==='account.php'?' active':'').'" href="'.$prefix.'account.php"'.($page==='account.php'?' aria-current="page"':'').'><i class="fas fa-lock" aria-hidden="true"></i> Account security</a></nav>';
    echo '<div class="sidebar-footer"><div class="user-info"><div class="user-avatar">'.portalEscape(mb_strtoupper(mb_substr($name,0,1))).'</div><div><div class="user-name">'.portalEscape($name).'</div><div class="user-role">'.portalEscape($role==='admin'?'Administrator':ucfirst($role)).'</div></div></div><a class="nav-item" href="'.$prefix.'logout.php"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sign Out</a></div></aside>';
}
function renderPortalPageStart(string $title,string $description='',string $contentClass='settings-content'): void {
    $prefix=strpos($_SERVER['SCRIPT_NAME']??'','/admin/')!==false?'../':'';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.portalEscape($title).' — Barangay Mudiang</title><link rel="stylesheet" href="'.$prefix.'assets/vendor/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
    include __DIR__.'/ui_theme.php';
    echo '</head><body class="portal-page">';renderPortalNavigation();
    echo '<main class="main"><header class="topbar"><div class="topbar-left"><span class="portal-kicker">Barangay Mudiang / Community services</span><h1>'.portalEscape($title).'</h1><p>'.portalEscape($description).'</p></div><div class="topbar-right"></div></header><div class="content '.portalEscape($contentClass).'">';
}
function renderPortalPageEnd(): void {echo '</div></main></body></html>';}
