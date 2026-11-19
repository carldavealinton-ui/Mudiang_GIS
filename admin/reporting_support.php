<?php
function reportingRange(array $input,?DateTimeImmutable $today=null): array {
    $today??=new DateTimeImmutable('today',new DateTimeZone('Asia/Manila'));
    $read=static function($key,$default)use($input){if(!isset($input[$key]))return $default;if(!is_string($input[$key]))throw new InvalidArgumentException('Choose valid report filters.');return trim($input[$key]);};
    $period=$read('period','yearly');
    if(!in_array($period,['daily','weekly','yearly'],true))throw new InvalidArgumentException('Choose Daily, Weekly, or Yearly.');
    $date=$read('date',$today->format('Y-m-d'));
    $anchor=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Asia/Manila'));
    if(!$anchor||$anchor->format('Y-m-d')!==$date)throw new InvalidArgumentException('Choose a valid report date.');
    $year=$read('year',$anchor->format('Y'));
    if(!preg_match('/^[0-9]{4}$/D',$year)||(int)$year<1900||(int)$year>2100)throw new InvalidArgumentException('Choose a report year from 1900 to 2100.');
    if($period==='daily'){$start=$anchor;$end=$start->modify('+1 day');$previous=$start->modify('-1 day');$next=$end;$label=$start->format('F j, Y');$bucket='hour';$title='Daily report';}
    elseif($period==='weekly'){$start=$anchor->modify('-'.((int)$anchor->format('N')-1).' days');$end=$start->modify('+7 days');$previous=$start->modify('-7 days');$next=$end;$label=$start->format('M j, Y').' – '.$end->modify('-1 day')->format('M j, Y');$bucket='day';$title='Weekly report';}
    else{$start=$anchor->setDate((int)$year,1,1);$end=$start->modify('+1 year');$previous=$start->modify('-1 year');$next=$end;$label=$year;$bucket='month';$title='Yearly report';}
    $category=$read('category','');$priority=$read('priority','');
    if($category!==''&&!in_array($category,['Road Damage','Drainage Issue','Street Light','Garbage Collection','Noise Complaint','Water Supply','Other'],true))throw new InvalidArgumentException('Choose a valid complaint category.');
    if($priority!==''&&!in_array($priority,['low','medium','high'],true))throw new InvalidArgumentException('Choose a valid importance level.');
    $params=['period'=>$period,'date'=>$date,'year'=>$year,'category'=>$category,'priority'=>$priority];
    $previousParams=$params;$previousParams['date']=$previous->format('Y-m-d');$previousParams['year']=$previous->format('Y');
    $nextParams=$params;$nextParams['date']=$next->format('Y-m-d');$nextParams['year']=$next->format('Y');
    return compact('period','start','end','previous','next','label','bucket','title','category','priority','params','previousParams','nextParams');
}
function reportingFilter(array $range,string $column='created_at'): array {
    if(!in_array($column,['created_at','resolved_at'],true))throw new InvalidArgumentException('Invalid report date column.');
    $where="$column>=? AND $column<?";$params=[$range['start']->format('Y-m-d H:i:s'),$range['end']->format('Y-m-d H:i:s')];
    foreach(['category'=>'complaint_type','priority'=>'priority'] as $key=>$field)if($range[$key]!==''){$where.=" AND $field=?";$params[]=$range[$key];}
    return [$where,$params];
}
function reportingData(PDO $pdo,array $range,int $requestedPage=1): array {
    [$where,$params]=reportingFilter($range);
    $q=$pdo->prepare("SELECT COUNT(*) AS total,
      COALESCE(SUM(status='filed'),0) AS filed,COALESCE(SUM(status='acknowledged'),0) AS acknowledged,
      COALESCE(SUM(status='processing'),0) AS processing,COALESCE(SUM(status='resolved'),0) AS resolved,
      COALESCE(SUM(priority='high'),0) AS high,COALESCE(SUM(priority='medium'),0) AS medium,COALESCE(SUM(priority='low'),0) AS low,
      COALESCE(SUM(status<>'resolved' AND due_date<CURDATE()),0) AS overdue,
      AVG(CASE WHEN status='resolved' AND resolved_at>=created_at THEN TIMESTAMPDIFF(SECOND,created_at,resolved_at)/86400 END) AS average_days
      FROM complaints WHERE $where");$q->execute($params);$stats=$q->fetch(PDO::FETCH_ASSOC);
    foreach(['total','filed','acknowledged','processing','resolved','high','medium','low','overdue'] as $key)$stats[$key]=(int)$stats[$key];
    $stats['open']=$stats['total']-$stats['resolved'];$stats['rate']=$stats['total']?round($stats['resolved']/$stats['total']*100,1):0;
    $previousRange=$range;$previousRange['start']=$range['previous'];$previousRange['end']=$range['start'];
    [$previousWhere,$previousParams]=reportingFilter($previousRange);$q=$pdo->prepare("SELECT COUNT(*) FROM complaints WHERE $previousWhere");$q->execute($previousParams);$previousTotal=(int)$q->fetchColumn();
    [$completionWhere,$completionParams]=reportingFilter($range,'resolved_at');$q=$pdo->prepare("SELECT COUNT(*) FROM complaints WHERE $completionWhere AND status='resolved'");$q->execute($completionParams);$completedInPeriod=(int)$q->fetchColumn();
    $expression=['hour'=>'HOUR(created_at)','day'=>'DATE(created_at)','month'=>'MONTH(created_at)'][$range['bucket']];
    $q=$pdo->prepare("SELECT $expression AS bucket,COUNT(*) AS total,SUM(status='resolved') AS resolved FROM complaints WHERE $where GROUP BY $expression ORDER BY bucket");$q->execute($params);$raw=$q->fetchAll(PDO::FETCH_ASSOC);$indexed=[];foreach($raw as $row)$indexed[(string)$row['bucket']]=$row;
    $trend=[];$count=['hour'=>24,'day'=>7,'month'=>12][$range['bucket']];
    for($i=0;$i<$count;$i++){
        if($range['bucket']==='hour'){$key=(string)$i;$label=sprintf('%02d:00',$i);}
        elseif($range['bucket']==='day'){$day=$range['start']->modify("+$i days");$key=$day->format('Y-m-d');$label=$day->format('D, M j');}
        else{$key=(string)($i+1);$label=$range['start']->modify("+$i months")->format('M');}
        $row=$indexed[$key]??['total'=>0,'resolved'=>0];$trend[]=['label'=>$label,'total'=>(int)$row['total'],'resolved'=>(int)$row['resolved']];
    }
    $q=$pdo->prepare("SELECT complaint_type,COUNT(*) AS total,SUM(status='resolved') AS resolved,SUM(status<>'resolved') AS open FROM complaints WHERE $where GROUP BY complaint_type ORDER BY total DESC,complaint_type");$q->execute($params);$categories=$q->fetchAll(PDO::FETCH_ASSOC);
    $pages=max(1,(int)ceil($stats['total']/20));$page=min($pages,max(1,$requestedPage));$offset=($page-1)*20;
    $q=$pdo->prepare("SELECT id,complaint_type,complainant_name,address,status,priority,assigned_to,due_date,created_at,resolved_at FROM complaints WHERE $where ORDER BY created_at DESC,id DESC LIMIT 20 OFFSET $offset");$q->execute($params);$records=$q->fetchAll(PDO::FETCH_ASSOC);
    return compact('stats','previousTotal','completedInPeriod','trend','categories','page','pages','records');
}
function reportingCsvCell($value): string {
    $value=(string)$value;
    return preg_match('/^[\s]*[=+\-@]/u',$value)||preg_match('/^[\t\r\n]/',$value)?"'".$value:$value;
}
function exportReportingCsv(PDO $pdo,array $range,array $data,string $kind): void {
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$range['period'].'-report-'.$range['start']->format('Y-m-d').'-'.$kind.'.csv"');header('Cache-Control: private, no-store');
    session_write_close();$out=fopen('php://output','w');echo "\xEF\xBB\xBF";
    $write=static function(array $row)use($out){fputcsv($out,array_map('reportingCsvCell',$row));};
    if($kind==='records'){
        $write(['Report ID','Category','Resident','Address','Current status','Importance','Assigned to','Target date','Filed at','Resolved at']);
        [$where,$params]=reportingFilter($range);$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
        $q=$pdo->prepare("SELECT id,complaint_type,complainant_name,address,status,priority,assigned_to,due_date,created_at,resolved_at FROM complaints WHERE $where ORDER BY created_at DESC,id DESC");$q->execute($params);while($row=$q->fetch(PDO::FETCH_NUM))$write($row);
    }else {
        $write(['Report period',$range['title']]);$write(['From',$range['start']->format('Y-m-d')]);$write(['Through',$range['end']->modify('-1 day')->format('Y-m-d')]);$write(['Timezone','Asia/Manila']);$write(['Category filter',$range['category']?:'All']);$write(['Importance filter',$range['priority']?:'All']);$write(['Basis','Reports filed in the selected period, using current status']);
        foreach(['total'=>'Reports filed','resolved'=>'Currently resolved','open'=>'Currently open','filed'=>'Awaiting acknowledgment','acknowledged'=>'Acknowledged','processing'=>'Processing','overdue'=>'Currently overdue','high'=>'High importance','rate'=>'Resolution rate (%)','average_days'=>'Average resolution days'] as $key=>$label)$write([$label,$data['stats'][$key]??'Not available']);
        $write(['Resolved during this period (all filing dates)',$data['completedInPeriod']]);$write(['Previous period filed',$data['previousTotal']]);$write([]);$write(['Time bucket','Filed','Currently resolved']);foreach($data['trend'] as $row)$write(array_values($row));
        $write([]);$write(['Category','Filed','Currently resolved','Currently open']);foreach($data['categories'] as $row)$write(array_values($row));
    }
    fclose($out);exit;
}
