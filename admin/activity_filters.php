<?php
function activityFilterQuery(array $input): array
{
    $filters=[];
    foreach(['q','actor','type','from','to'] as $key) $filters[$key]=is_string($input[$key]??null)?trim($input[$key]):'';
    $filters['q']=mb_substr($filters['q'],0,100);
    $where=[];$params=[];$error='';
    if($filters['q']!==''){
        $where[]='(CAST(a.complaint_id AS CHAR) LIKE ? OR c.complainant_name LIKE ? OR c.complaint_type LIKE ? OR a.summary LIKE ?)';
        array_push($params,...array_fill(0,4,'%'.$filters['q'].'%'));
    }
    if($filters['actor']!==''){
        if(!ctype_digit($filters['actor']))$error='Choose a valid staff member.';
        else{$where[]='a.actor_id=?';$params[]=(int)$filters['actor'];}
    }
    $types=['filed'=>'Report filed','status'=>'Status changed','remark'=>'Remark added','message'=>'Staff message','assignment'=>'Assignment changed','importance'=>'Importance changed','update'=>'Resolution notes updated','deadline'=>'Deadline changed','proof'=>'Completion evidence','confirmation'=>'Resident confirmation','reopen'=>'Reopening requested'];
    if($filters['type']!==''){
        if(!isset($types[$filters['type']]))$error='Choose a valid action.';
        else{$where[]='a.event_type=?';$params[]=$filters['type'];}
    }
    foreach(['from','to'] as $key){
        if($filters[$key]==='')continue;
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters[$key])){$error='Enter valid dates in the date filters.';continue;}
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$filters[$key]);
        if(!$date||$date->format('Y-m-d')!==$filters[$key]){$error='Enter valid dates in the date filters.';continue;}
        $where[]=$key==='from'?'a.created_at>=?':'a.created_at<?';
        $params[]=$key==='from'?$date->format('Y-m-d H:i:s'):$date->modify('+1 day')->format('Y-m-d H:i:s');
    }
    if($filters['from']!==''&&$filters['to']!==''&&$filters['from']>$filters['to'])$error='The start date must be on or before the end date.';
    if($error)$where[]='1=0';
    return ['filters'=>$filters,'where'=>$where?' WHERE '.implode(' AND ',$where):'','params'=>$params,'error'=>$error,'types'=>$types];
}
