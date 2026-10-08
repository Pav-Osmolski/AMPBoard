<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
use AMPBoard\Folders\UrlRules;
use AMPBoard\Folders\LinkTemplates;
use AMPBoard\Filesystem\DirectoryCatalog;
use AMPBoard\Ui\FolderPresenter;
function check( bool $value, string $message ): void { if ( ! $value ) { throw new RuntimeException( $message ); } }
$handler = static function ( $severity, $message, $file, $line ) {
 if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
 return false;
};
set_error_handler( $handler );
$rules = new UrlRules();
check( $rules->apply('project',[]) === ['name'=>'project','errors'=>[]], 'Unmodified name' );
$column = ['urlRules'=>['match'=>'/^dev-/','replace'=>'/^dev-/'], 'specialCases'=>['shop'=>'shop.test']];
check( $rules->apply('dev-shop',$column)['name'] === 'shop.test', 'Removal regex before special case' );
check( $rules->apply('shop',$column)['name'] === null, 'Nonmatching folder skipped before special cases' );
check( $rules->apply('__SKIP__',[])['name'] === null, 'Historical sentinel preserved' );
check( $rules->apply('x',['specialCases'=>['x'=>'']])['name'] === '', 'Empty special case remains visible' );
foreach ( [ ['match'=>'','replace'=>'/x/'], ['match'=>'/[/', 'replace'=>'/x/'], ['match'=>'/x/','replace'=>'/[/' ] ] as $rule ) {
 $result = $rules->apply('x',['title'=>'<unsafe & title>','urlRules'=>$rule]);
 check($result['name']==='x' && count($result['errors'])===1 && str_contains($result['errors'][0],'<unsafe & title>'),'Invalid rules retain folder and plain diagnostic');
}
$prior = set_error_handler(static function(){return true;}); restore_error_handler();
check($prior === $handler,'Regex operations restore caller error handler');
$templates = new LinkTemplates([ ['name'=>'basic','html'=>'old'], ['name'=>'basic','html'=>'<li><a href="https://{urlName}.test">{urlName}</a></li>'], 'invalid' ]);
check(str_contains($templates->html('unknown'),'https://'),'Last duplicate wins; unknown uses basic');
check(LinkTemplates::resolve('missing',[])==='<li><a href="/{urlName}">{urlName}</a></li>','Built-in fallback');
$markup=LinkTemplates::render('<li><a href="/{urlName}">{urlName}</a><span>extra</span></li>', '<tag>&"', false);
check(!str_contains($markup,'<tag>') && str_contains($markup,'&lt;tag&gt;&amp;&quot;'),'Placeholder escaping');
check(LinkTemplates::render('<li><a href="/x">{urlName}</a><span>extra</span></li>','name',true)==='<li>name<span>extra</span></li>','Disabled links keep structural markup');
check(str_contains(LinkTemplates::render('{urlName}',"bad\xFF",false),"bad\xEF\xBF\xBD"),'Invalid UTF-8 substitution');
check(LinkTemplates::hosts('<a href="https://{urlName}.TEST/x">x</a><a href="//{urlName}.test">y</a><a href="/relative">z</a>','Shop')===['shop.test'],'Unique lowercase hosts and relative links');
$root=sys_get_temp_dir().'/ampboard-folder-view-'.bin2hex(random_bytes(8));
mkdir($root.'/sites/dev-shop',0700,true);mkdir($root.'/sites/dev-other');mkdir($root.'/sites/plain');mkdir($root.'/empty');mkdir($root.'/second/project',0700,true);
register_shutdown_function(static function()use($root):void{
 $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($items as $item){$item->isDir()&&!$item->isLink()?rmdir($item->getPathname()):unlink($item->getPathname());}rmdir($root);
});
$calls=[];
$valid=static function(string $host)use(&$calls):bool{$calls[]=$host;return $host==='store.test';};
$columns=[array_replace($column,['title'=>'Projects','specialCases'=>['shop'=>'store'],'dir'=>'sites','requireVhost'=>true,'excludeList'=>['dev-other']]), ['title'=>'No links','dir'=>'sites','disableLinks'=>true,'excludeList'=>['plain']], ['title'=>'Gone','dir'=>'missing'], ['title'=>'Empty','dir'=>'empty'], ['title'=>'Blocked <x>','dir'=>'../bad'], 'bad column'];
$presenter=new FolderPresenter($columns,$templates,new DirectoryCatalog($root),$valid);
check($calls===[],'Construction performs no host checks');
$view=$presenter->prepare();
check($view['empty']===null && count($view['columns'])===5 && $view['hasVhostFilteredColumns'],'Prepared columns and badges');
check(count($view['columns'][0]['items'])===1 && $view['columns'][0]['items'][0]['name']==='store','Combined exclusions, regex, special cases and vhost filtering');

check($calls===['store.test'],'Only eligible folders invoke vhost discovery');
check(count($view['columns'][1]['items'])===2 && !str_contains($view['columns'][1]['items'][0]['html'],'href='),'Disabled links and strict exclusions');
check(array_column(array_slice($view['columns'],2),'state')===['missing','empty','missing'],'Missing/empty/traversal states');
check(count($view['errors'])===2,'Invalid columns and traversal diagnostics');
$multiple=new FolderPresenter([['dir'=>'sites','requireVhost'=>true]],new LinkTemplates([['name'=>'basic','html'=>'<li><a href="https://missing.test">{urlName}</a><a href="https://store.test">open</a></li>']]),new DirectoryCatalog($root),$valid);
check(count($multiple->prepare()['columns'][0]['items'])===3,'Any template host can satisfy vhost filter');
$other=new FolderPresenter([['dir'=>'second']],$templates,new DirectoryCatalog($root),static function(){throw new RuntimeException('Unexpected host probe');});
check($other->prepare()['columns'][0]['items'][0]['folder']==='project','Independent supplied columns; non-vhost panels do not probe');
$filtered=new FolderPresenter([['dir'=>'sites','excludeList'=>['dev-shop','dev-other','plain']]],$templates,new DirectoryCatalog($root),$valid);
check($filtered->prepare()['columns'][0]['state']==='ready' && $filtered->prepare()['columns'][0]['items']===[],'All filtered is distinct from empty directory');
foreach([[[],[],'both'],[[],[['name'=>'basic']],'folders'],[[['dir'=>'sites']],[],'templates']] as [$cols,$tpls,$expected]){
 $empty=new FolderPresenter($cols,new LinkTemplates($tpls),new DirectoryCatalog($root),$valid);
 check($empty->prepare()['empty']===$expected,'Empty configuration state');
}
$malformed=new FolderPresenter([['title'=>'<unsafe>','dir'=>'sites','urlRules'=>['match'=>'/[/', 'replace'=>'/x/']]],$templates,new DirectoryCatalog($root),$valid);
check(count($malformed->prepare()['errors'])===1,'Duplicate rule warnings deduplicated');
require __DIR__.'/../config/helpers/templates.php';
$errors=[];
check(build_url_name('dev-shop',$column,$errors)==='shop.test' && $errors===[],'Compatibility URL helper');
check(resolve_template_html('x',[])===LinkTemplates::resolve('x',[]) && render_item_html('{urlName}','<&',false)==='&lt;&amp;','Compatibility template helpers');
check(extract_template_hosts_for_url('<a href="//{urlName}.test">x</a>','SHOP')===['shop.test'],'Compatibility host helper');
// Render the actual panel with supplied services; it does not load installed profiles.
mkdir($root.'/partials');mkdir($root.'/config');
copy(__DIR__.'/../partials/folders.php',$root.'/partials/folders.php');
file_put_contents($root.'/config/config.php','<?php /* fixture dependencies */');
file_put_contents( $root . '/config/entry-folders.php', '<?php require_once __DIR__ . "/config.php";' );

$config=['paths'=>['assets'=>dirname(__DIR__).'/assets'], 'ui'=>['flags'=>['folderBadges'=>true]],'status'=>['apachePathValid'=>true]];
$ui=new AMPBoard\Ui\Renderer($config);$folderPresenter=$presenter;
ob_start();require $root.'/partials/folders.php';$html=ob_get_clean();
check(str_contains($html,'id="column_1"') && str_contains($html,'data-width-key="width_columns"') && str_contains($html,'No Links'),'Panel layout, IDs and badges');
check(str_contains($html,'https://store.test') && !str_contains($html,'<x>') && str_contains($html,'Blocked &lt;x&gt;'),'Prepared links and escaped warnings');
$folderPresenter=$malformed;ob_start();require $root.'/partials/folders.php';$html=ob_get_clean();
check(str_contains($html,'Invalid regex') && !str_contains($html,'<unsafe>') && !str_contains($html,'&amp;lt;unsafe'),'Warnings escaped exactly once');
foreach([[[],[],'No folders or link templates'],[[],[['name'=>'basic']],'No folders configured'],[[['dir'=>'sites']],[],'No link templates']] as [$cols,$tpls,$text]){
 $folderPresenter=new FolderPresenter($cols,new LinkTemplates($tpls),new DirectoryCatalog($root),$valid);
 ob_start();require $root.'/partials/folders.php';$html=ob_get_clean();check(str_contains($html,$text),'Panel empty-state wording');
}
echo "PASS folder presentation services and panel\n";
