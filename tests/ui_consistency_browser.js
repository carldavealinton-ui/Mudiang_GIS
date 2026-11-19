const {spawn,execFileSync}=require('node:child_process');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),assert=require('node:assert/strict');
const php='C:/xampp/php/php.exe';const fixture=JSON.parse(execFileSync(php,['tests/resident_browser_fixture.php'],{encoding:'utf8'}));
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'mudiang-ui-'));const artifacts=path.resolve('storage/ui-review');fs.mkdirSync(artifacts,{recursive:true});
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--remote-debugging-port=9228',`--user-data-dir=${profile}`,'--no-first-run','--no-default-browser-check','--disable-gpu','about:blank'],{windowsHide:true,stdio:'ignore'});
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));let socket;
async function run(){
 let pages;for(let i=0;i<30;i++){try{pages=await(await fetch('http://127.0.0.1:9228/json')).json();break;}catch(_){await sleep(200);}}if(!pages)throw new Error('Chrome unavailable.');
 socket=new WebSocket(pages.find(page=>page.type==='page').webSocketDebuggerUrl);await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
 let sequence=0;const pending=new Map(),errors=[];
 socket.addEventListener('message',event=>{const data=JSON.parse(event.data);if(data.id){const task=pending.get(data.id);pending.delete(data.id);if(data.error)task.reject(new Error(data.error.message));else task.resolve(data.result);}if(data.method==='Runtime.exceptionThrown')errors.push(data.params.exceptionDetails.exception?.description||data.params.exceptionDetails.text);});
 function command(method,params={}){return new Promise((resolve,reject)=>{const id=++sequence;pending.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params}));});}
 async function evaluate(expression){const result=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw new Error(result.exceptionDetails.exception?.description||'Evaluation failed.');return result.result.value;}
 async function until(expression){for(let i=0;i<70;i++){if(await evaluate(expression))return;await sleep(100);}throw new Error('Timed out: '+expression);}
 async function visit(route){await command('Page.navigate',{url:'http://localhost/System/'+route});await until("document.readyState==='complete' && Boolean(document.querySelector('link[href*=\"portal-consistency.css\"]'))");await sleep(100);}
 async function screenshot(name){await sleep(750);const result=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(artifacts,name+'.png'),Buffer.from(result.data,'base64'));}
 await command('Page.enable');await command('Runtime.enable');await command('Network.enable');
 const routes={resident:['resident_dashboard.php','submit_complaint_form.php','messages.php','complaint_conversation.php?id='+fixture.reports.resident,'profile.php','account.php'],admin:['admin/dashboard.php','admin/operations.php','admin/messages.php','admin/residents.php','admin/activity.php','admin/annual_report.php','admin/edit_complaint.php?id='+fixture.reports.resident,'admin/announcements.php','admin/staff.php','admin/account_access.php','account.php'],staff:['admin/staff_dashboard.php','admin/messages.php','admin/activity.php','admin/edit_complaint.php?id='+fixture.reports.resident,'account.php']};
 for(const role of ['resident','admin','staff']){
   const response=await fetch('http://localhost/System/index.php',{method:'POST',body:new URLSearchParams({username:fixture.accounts[role],password:fixture.password}),redirect:'manual'});assert.equal(response.status,302);
   const cookie=response.headers.getSetCookie().filter(value=>value.startsWith('PHPSESSID=')).at(-1).split(';')[0].slice(10);await command('Network.setCookie',{name:'PHPSESSID',value:cookie,url:'http://localhost/System/'});
   let expectedNav;
   for(const width of [1440,390]){
     await command('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width===390});
     for(const route of routes[role]){
       await visit(route);
       const metrics=await evaluate(`(()=>{const nav=document.querySelector('.sidebar-nav'),main=document.querySelector('body>.main'),h1=document.querySelector('.topbar h1');return {sidebars:document.querySelectorAll('body>.sidebar').length,nav:[...nav.querySelectorAll('a')].map(a=>a.textContent.trim()),active:nav.querySelectorAll('[aria-current="page"]').length,overflow:document.documentElement.scrollWidth>innerWidth+1,font:getComputedStyle(h1).fontSize,mainMargin:getComputedStyle(main).marginLeft,bg:getComputedStyle(document.querySelector('.topbar')).backgroundColor};})()`);
       assert.equal(metrics.sidebars,1,route+' one sidebar');assert.equal(metrics.active,1,route+' one active item');assert(!metrics.overflow,route+' no horizontal overflow');assert.equal(metrics.font,width===1440?'26px':'22px',route+' consistent page heading');assert.equal(metrics.mainMargin,width===1440?'260px':'0px',route+' consistent content alignment');
       const controls=await evaluate(`(()=>{const header=document.querySelector('.topbar');return {actions:header.querySelectorAll(':scope>.topbar-right').length,looseActions:[...header.children].filter(el=>!el.matches('.topbar-left,.topbar-right,.mobile-menu')).length,buttons:[...document.querySelectorAll('.content .btn:not(.btn-sm),.report-button,.report-filters button')].filter(el=>el.getClientRects().length).map(el=>({height:parseFloat(getComputedStyle(el).minHeight),radius:getComputedStyle(el).borderRadius})),inputs:[...document.querySelectorAll('.report-filters input,.report-filters select')].filter(el=>el.getClientRects().length).map(el=>getComputedStyle(el).borderRadius)};})()`);
       assert.equal(controls.actions,1,route+' one header action area');assert.equal(controls.looseActions,0,route+' actions grouped in header');
       assert(await evaluate("Boolean(document.querySelector('body>.main>.content'))"),route+' content inside shared container');
       for(const button of controls.buttons){assert(button.height>=44,route+' standard button height');assert.equal(button.radius,'9px',route+' standard button shape');}
       for(const radius of controls.inputs)assert.equal(radius,'9px',route+' standard report input shape');
       if(role==='resident'&&width===1440&&route==='resident_dashboard.php'){
         await until("Boolean(document.querySelector('.sidebar-nav .messages-nav-count'))");
         await evaluate(`window.messageAlertOriginalFetch=window.fetch;window.fetch=(url,options)=>String(url).startsWith('resident_message_notifications.php')?Promise.resolve({ok:true,json:async()=>({count:1,cursor:0,items:[{id:1,complaint_id:${fixture.reports.resident},sender_name:'Test administrator',body:'Your report has an update.'}]})}):window.messageAlertOriginalFetch(url,options);document.dispatchEvent(new Event('visibilitychange'));`);
         await until("Boolean(document.querySelector('.resident-message-toasts .admin-toast'))");
         assert.equal(await evaluate("document.querySelector('.sidebar-nav .messages-nav-count').textContent"),'1');
         assert.equal(await evaluate("document.querySelector('.resident-message-toasts a').getAttribute('href')"),'messages.php?id='+fixture.reports.resident);
         await evaluate("window.fetch=window.messageAlertOriginalFetch;delete window.messageAlertOriginalFetch;document.querySelector('.resident-message-toasts button').click()");
         assert(await evaluate("!document.querySelector('.resident-message-toasts .admin-toast')"),'Resident can dismiss message popup');
         console.log('PASS: Resident message icon badge, popup link and dismiss button');
       }
       if(!expectedNav)expectedNav=metrics.nav;else assert.deepEqual(metrics.nav,expectedNav,role+' consistent navigation across pages');
       if(width===390){await evaluate("document.querySelector('.mobile-menu').click()");assert(await evaluate("document.querySelector('.sidebar').classList.contains('is-open')"));await evaluate("document.querySelector('.sidebar-backdrop').click()");}
       if(['admin/annual_report.php','admin/announcements.php'].includes(route)||(width===1440&&['profile.php','admin/dashboard.php','messages.php'].includes(route))||(width===390&&route==='account.php'))await screenshot(role+'-'+route.replace(/[/?=]/g,'-')+'-'+width);
     }
     console.log(`PASS: ${role} navigation, headings, layout and menu on ${routes[role].length} pages at ${width}px`);
   }
 }
 await command('Network.clearBrowserCookies');
 for(const width of [1440,390]){
   await command('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width===390});
   for(const route of ['index.php','register.php','forgot_password.php','reset_password.php','verify_email.php','admin/login.php']){
     await visit(route);assert(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),route+' auth fits viewport');
     if(route==='register.php'){
       await evaluate("document.getElementById('password').value='Password123';document.getElementById('password').dispatchEvent(new Event('input'));document.getElementById('confirm_password').value='Password123';document.getElementById('confirm_password').dispatchEvent(new Event('input'));document.querySelector('[data-password=\"password\"]').click()");
       assert.equal(await evaluate("document.getElementById('password').type"),'text');assert(await evaluate("document.getElementById('passwordMatch').classList.contains('met')"));
       await screenshot('registration-'+width);
     }
   }
   console.log('PASS: Six authentication pages fit '+width+'px');
 }
 assert.equal(errors.length,0,errors.join('\n'));console.log('UI checks passed without JavaScript exceptions. Screenshots: storage/ui-review');await command('Browser.close');
}
run().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(socket)socket.close();browser.kill();execFileSync(php,['tests/resident_browser_fixture.php','cleanup',...fixture.users.map(String)],{stdio:'ignore'});await sleep(600);const target=path.resolve(profile);if(!target.startsWith(path.resolve(os.tmpdir())+path.sep)||!path.basename(target).startsWith('mudiang-ui-'))throw new Error('Cleanup target rejected.');try{fs.rmSync(target,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch(_){}});
