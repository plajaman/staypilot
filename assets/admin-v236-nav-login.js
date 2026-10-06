// StayPilot V2.3.6.66 – Admin-Navigation & Login-Komfort
(function(){
  'use strict';
  function ready(fn){ if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
  function groupNavigation(){
    var nav=document.getElementById('nav');
    if(!nav || nav.dataset.v23666Grouped==='1') return;
    nav.dataset.v23666Grouped='1';
    var nodes=Array.from(nav.children);
    var frag=document.createDocumentFragment();
    var current=null;
    function makeGroup(title){
      var details=document.createElement('details'); details.className='sp-nav-group'; details.open=true;
      var summary=document.createElement('summary'); summary.innerHTML='<span>'+title+'</span><b>⌄</b>'; details.appendChild(summary);
      var body=document.createElement('div'); body.className='sp-nav-group-body'; details.appendChild(body);
      frag.appendChild(details); return body;
    }
    nodes.forEach(function(node){
      if(node.classList && node.classList.contains('nav-title')){
        current=makeGroup(node.textContent.trim() || 'Bereich');
        return;
      }
      if(!current) current=makeGroup('Start');
      current.appendChild(node);
    });
    nav.innerHTML=''; nav.appendChild(frag);
    var saved=JSON.parse(localStorage.getItem('spNavGroupsV23666')||'{}');
    nav.querySelectorAll('.sp-nav-group').forEach(function(group){
      var title=group.querySelector('summary span')?.textContent || '';
      if(Object.prototype.hasOwnProperty.call(saved,title)) group.open=!!saved[title];
      group.addEventListener('toggle',function(){
        var data={}; nav.querySelectorAll('.sp-nav-group').forEach(function(g){data[g.querySelector('summary span')?.textContent||'']=g.open;});
        localStorage.setItem('spNavGroupsV23666', JSON.stringify(data));
      });
    });
  }
  function navOverview(){
    var btn=document.getElementById('spOpenNavOverview');
    if(!btn) return;
    btn.addEventListener('click', function(){
      var nav=document.getElementById('nav'); if(!nav) return;
      var groups=Array.from(nav.querySelectorAll('.sp-nav-group')).map(function(g){
        var title=g.querySelector('summary span')?.textContent || 'Bereich';
        var count=g.querySelectorAll('button[data-page]').length;
        return '<button type="button" class="btn block" data-v23666-group="'+title.replace(/&/g,'&amp;').replace(/"/g,'&quot;')+'"><b>'+title+'</b><span class="muted">'+count+' Seiten</span></button>';
      }).join('');
      if(typeof modal==='function'){
        modal('Admin-Menü', '<div class="sp-nav-overview">'+groups+'</div>', '<button class="btn primary" type="button" data-close-modal>Schließen</button>', true);
      }else{
        document.getElementById('sidebar')?.classList.toggle('open');
      }
    });
    document.addEventListener('click', function(ev){
      var g=ev.target.closest('[data-v23666-group]'); if(!g) return;
      var title=g.dataset.v23666Group;
      document.querySelectorAll('#nav .sp-nav-group').forEach(function(group){
        if((group.querySelector('summary span')?.textContent||'')===title){group.open=true; group.scrollIntoView({block:'center'});}
      });
      if(typeof closeModal==='function') closeModal(true);
      document.getElementById('sidebar')?.classList.add('open');
    });
  }
  ready(function(){ groupNavigation(); navOverview(); });
})();
