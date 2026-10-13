window.CEPForm=(function(){
  function digits(v){return String(v||'').replace(/\D/g,'').slice(0,8);}
  function formatar(v){
    const d=digits(v);
    if(d.length<=5) return d;
    return d.slice(0,5)+'-'+d.slice(5);
  }
  async function consultar(cep, ids){
    const clean=digits(cep); if(clean.length!==8) return;
    try{
      const r=await fetch((window.SITE_BASE_URL||'')+'/api/cep.php?cep='+encodeURIComponent(clean),{headers:{Accept:'application/json'},cache:'no-store'});
      const d=await r.json();
      if(!r.ok||d.erro) throw new Error(d.mensagem||'CEP não encontrado.');
      Object.entries({logradouro:d.logradouro,bairro:d.bairro,cidade:d.cidade,uf:(d.uf||d.estado)}).forEach(([k,v])=>{const el=document.getElementById(ids[k]);if(el)el.value=v||'';});
    }catch(e){alert(e.message||'Não foi possível consultar o CEP.');}
  }
  function bind(id,ids){
    const el=document.getElementById(id); if(!el)return;
    const aplicarMascara=()=>{el.value=formatar(el.value);};
    el.addEventListener('input',aplicarMascara);
    el.addEventListener('paste',()=>setTimeout(aplicarMascara,0));
    el.addEventListener('blur',()=>{aplicarMascara(); consultar(el.value,ids);});
    aplicarMascara();
  }
  return {bind,formatar};
})();
