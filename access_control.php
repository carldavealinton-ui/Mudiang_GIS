<?php
function canAccessComplaint(array $complaint): bool {
    $role=$_SESSION['role']??'';$id=(int)($_SESSION['user_id']??0);
    return $id>0&&($role==='admin'||($role==='staff'&&(int)($complaint['assigned_staff_id']??0)===$id)||($role==='resident'&&(int)($complaint['user_id']??0)===$id));
}
function complaintAccessScope(string $alias='c'): array {
    if(($_SESSION['role']??'')==='admin')return ['1=1',[]];
    $column=($_SESSION['role']??'')==='staff'?'assigned_staff_id':'user_id';
    return [$alias.'.'.$column.'=?',[(int)($_SESSION['user_id']??0)]];
}
function staffCanSetStatus(string $current,string $requested): bool {
    $allowed=['filed'=>['filed','acknowledged'],'acknowledged'=>['acknowledged','processing'],'processing'=>['processing','resolved'],'resolved'=>['resolved']];
    return in_array($requested,$allowed[$current]??[],true);
}
