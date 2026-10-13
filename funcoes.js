// =============================================================
// funcoes.js — Sistema LGPD
// Salvar em: /htdocs/assets/js/funcoes.js
// =============================================================


// ── MODAL DE IMAGEM (guia_lgpd.php) ─────────────────────────

function openModal(src) {
  document.getElementById('modal-img').src = src;
  document.getElementById('img-modal').classList.add('active');
}

function closeModal() {
  document.getElementById('img-modal').classList.remove('active');
}


// ── NAVEGAÇÃO SPA (painel.php) ───────────────────────────────

const meta = {
  painel:         { title: 'Painel geral',       sub: 'Bem-vinda, USUÁRIO' },
  clientes:       { title: 'Clientes',            sub: 'Cadastro e gestão de clientes' },
  consentimentos: { title: 'Consentimentos',      sub: 'Templates e controle de autorizações' },
  guia:           { title: '📚 Guia LGPD',        sub: 'Conteúdo educativo para o seu negócio' },
  conformidade:   { title: 'Minha conformidade',  sub: 'Mapa de dados e solicitações de titulares' }
};

function go(name) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('screen-' + name).classList.add('active');
  const nav = document.getElementById('nav-' + name);
  if (nav) nav.classList.add('active');
  document.getElementById('tb-title').textContent = meta[name].title;
  document.getElementById('tb-sub').textContent   = meta[name].sub;
}


// ── ABAS (clientes.php) ──────────────────────────────────────

function trocarAba(nome, atualizarUrl = true) {
  document.querySelectorAll('.aba').forEach(a => a.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('aba-' + nome).classList.add('active');
  const btn = document.querySelector('[data-aba="' + nome + '"]');
  if (btn) btn.classList.add('active');

  if (atualizarUrl) {
    const params = new URLSearchParams(window.location.search);
    params.set('aba', nome);
    history.pushState(null, '', 'clientes.php?' + params.toString());
  }
}

// ── ORDENAÇÃO DA TABELA (clientes.php) ──────────────────────

function ordenar(campo) {
  const params       = new URLSearchParams(window.location.search);
  const ordenarAtual = params.get('ordenar') || 'nome_completo';
  const ordemAtual   = params.get('ordem')   || 'ASC';
  const novaOrdem    = (ordenarAtual === campo && ordemAtual === 'ASC') ? 'DESC' : 'ASC';
  params.set('ordenar', campo);
  params.set('ordem',   novaOrdem);
  params.set('pagina',  '1');
  window.location.href = 'clientes.php?' + params.toString();
}


// ── CONSULTA AVANÇADA (clientes.php) ────────────────────────

function adicionarFiltro() {
  const container = document.getElementById('filtros-container');
  const total     = container.querySelectorAll('.filtro-item').length;
  const div       = document.createElement('div');
  div.className   = 'filtro-item';
  div.innerHTML   = `
    <div class="filtro-header">
      <strong>Filtro ${total + 1}</strong>
      <button type="button" class="btn-remover-filtro" onclick="removerFiltro(this)">✖ Remover</button>
    </div>
    <div class="filtro-grid">
      <div>
        <label>Coluna</label>
        <select name="filtros[${total}][coluna]">
          <option value="">Selecione...</option>
          ${colunaOptions()}
        </select>
      </div>
      <div>
        <label>Operador</label>
        <select name="filtros[${total}][operador]">
          ${operadorOptions()}
        </select>
      </div>
      <div>
        <label>Valor</label>
        <input type="text" name="filtros[${total}][valor]" placeholder="Digite o valor...">
      </div>
    </div>
  `;
  container.appendChild(div);
}

function removerFiltro(btn) {
  btn.closest('.filtro-item').remove();
}

function limparConsulta() {
  if (confirm('Limpar todos os filtros?')) window.location.href = 'clientes.php';
}

function colunaOptions() {
  const cols = {
    cpf:                          'CPF',
    nome_completo:                'Nome Completo',
    sexo:                         'Sexo',
    data_de_nascimento:           'Data de Nascimento',
    idade:                        'Idade',
    autorizacao:                  'Autorização LGPD',
    cep:                          'CEP',
    endereco:                     'Endereço',
    bairro:                       'Bairro',
    cidade:                       'Cidade',
    estado:                       'Estado',
    e_mail:                       'E-mail',
    celular_whatsapp:             'Celular/WhatsApp',
    forma_de_pagamento_preferida: 'Forma de Pagamento',
    cor_preferida:                'Cor Preferida',
    tecido_preferido:             'Tecido Preferido',
    tamanho_de_camiseta:          'Tamanho Camiseta',
    tamanho_de_calca:             'Tamanho Calça',
    tamanho_de_sapato:            'Tamanho Sapato'
  };
  return Object.entries(cols)
    .map(([v, l]) => `<option value="${v}">${l}</option>`)
    .join('');
}

function operadorOptions() {
  const ops = {
    '=':  'Igual a',
    LIKE: 'Contém',
    '>':  'Maior que',
    '<':  'Menor que',
    '>=': 'Maior ou igual',
    '<=': 'Menor ou igual',
    '!=': 'Diferente de',
    IN:   'Está entre'
  };
  return Object.entries(ops)
    .map(([v, l]) => `<option value="${v}">${l}</option>`)
    .join('');
}





// ── CNPJ (V03) ───────────────────────────────────────────────
// Exibe a máscara enquanto o usuário digita, sem alterar o valor
// enviado ao PHP: o controller remove a pontuação antes de salvar.
window.CNPJMask = {
  formatar(value) {
    // CNPJ: 00.000.000/0000-00
    // A pontuação aparece progressivamente enquanto o usuário digita.
    const d = String(value || '').replace(/\D/g, '').slice(0, 14);
    if (d.length <= 2) return d;
    if (d.length <= 5) return d.slice(0, 2) + '.' + d.slice(2);
    if (d.length <= 8) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5);
    if (d.length <= 12) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8);
    return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8, 12) + '-' + d.slice(12, 14);
  },
  bind(id) {
    const input = document.getElementById(id);
    if (!input) return;
    input.value = this.formatar(input.value);
    const validar = () => {
      const digits = String(input.value || '').replace(/\D/g, '');
      if (digits.length === 0) input.setCustomValidity('Informe o CNPJ.');
      else if (digits.length < 14) input.setCustomValidity('O CNPJ deve conter 14 dígitos.');
      else input.setCustomValidity('');
    };
    input.addEventListener('input', () => {
      input.value = this.formatar(input.value);
      input.setSelectionRange(input.value.length, input.value.length);
      validar();
    });
    input.addEventListener('blur', validar);
    validar();
  }
};

// ── CEP / ENDEREÇO (V03) ─────────────────────────────────────
function configurarBuscaCep(cfg) {
  const cepInput = document.getElementById(cfg.cep);
  if (!cepInput) return;

  const campo = nome => document.getElementById(cfg[nome]) || document.querySelector('[name="' + cfg[nome] + '"]');
  const endereco = campo('endereco'), bairro = campo('bairro'), cidade = campo('cidade'), estado = campo('estado');
  const status = document.getElementById(cfg.status || 'cep-status');
  const setStatus = (msg, ok=false) => { if (status) { status.textContent = msg; status.className = 'cep-status ' + (ok ? 'ok' : 'erro'); } };

  cepInput.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 8);
    if (v.length > 5) v = v.slice(0,5) + '-' + v.slice(5);
    this.value = v;
    if (v.replace(/\D/g,'').length < 8) setStatus('');
  });

  cepInput.addEventListener('blur', async function () {
    const cep = this.value.replace(/\D/g, '');
    if (cep.length !== 8) return;
    setStatus('Consultando CEP...');
    try {
      const base = (window.SITE_BASE_URL || '').replace(/\/$/, '');
      const r = await fetch(base + '/api/cep.php?cep=' + encodeURIComponent(cep), {headers:{'Accept':'application/json'}});
      const d = await r.json();
      if (!r.ok || d.erro) throw new Error(d.mensagem || 'CEP não encontrado.');
      if (endereco) endereco.value = d.logradouro || '';
      if (bairro) bairro.value = d.bairro || '';
      if (cidade) cidade.value = d.cidade || '';
      if (estado) estado.value = d.estado || '';
      setStatus('Endereço preenchido automaticamente.', true);
      const numero = campo('numero');
      if (numero) numero.focus();
    } catch (e) {
      setStatus(e.message || 'Não foi possível consultar o CEP.');
    }
  });
}

function aplicarEscalaFonte(scale) {
  scale = Math.min(1.3, Math.max(0.9, Number(scale) || 1));
  document.documentElement.style.setProperty('--font-scale', scale.toFixed(2));
  document.documentElement.setAttribute('data-font-scale', String(scale.toFixed(2)));
  localStorage.setItem('erp_font_scale', String(scale.toFixed(2)));

  // Mantém todos os elementos da interface — inclusive os que possuem
  // font-size definido em px — sujeitos ao aumento de acessibilidade.
  document.body.style.zoom = scale.toFixed(2);
  document.body.style.width = '100%';
  document.body.style.height = 'auto';

  document.querySelectorAll('.accessibility-tools button').forEach(function(btn) {
    btn.classList.remove('active');
  });
  const active = document.querySelector(
    '.accessibility-tools button[data-scale="' + scale.toFixed(2) + '"]'
  );
  if (active) active.classList.add('active');
}

function ajustarFonte(delta) {
  let scale = parseFloat(localStorage.getItem('erp_font_scale') || '1');
  scale = delta === 0 ? 1 : Math.min(1.3, Math.max(0.9, scale + delta));
  aplicarEscalaFonte(scale);
}

(function(){
  const scale = parseFloat(localStorage.getItem('erp_font_scale') || '1');
  document.addEventListener('DOMContentLoaded', function () {
    aplicarEscalaFonte(scale);
  });
})();

// ── INICIALIZAÇÃO ────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {

  // Registra clique das abas via JS — elimina dependência do onclick inline
  document.querySelectorAll('.tab-btn[data-aba]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      trocarAba(this.dataset.aba);
    });
  });


  // Máscara CPF: 000.000.000-00
  const cpfInput = document.getElementById('cpf');
  if (cpfInput) {
    cpfInput.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').slice(0, 11);
      v = v.replace(/(\d{3})(\d)/,       '$1.$2');
      v = v.replace(/(\d{3})(\d)/,       '$1.$2');
      v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      this.value = v;
    });
  }

  // Máscara CEP + consulta pela API interna V03.
  configurarBuscaCep({
    cep: 'cep', endereco: 'endereco', bairro: 'bairro', cidade: 'cidade', estado: 'estado'
  });

  // Máscara Celular: (00) 00000-0000
  const celInput = document.getElementById('celular');
  if (celInput) {
    celInput.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').slice(0, 11);
      v = v.replace(/^(\d{2})(\d)/, '($1) $2');
      v = v.replace(/(\d{5})(\d)/,  '$1-$2');
      this.value = v;
    });
  }

});

    function abrirVer(nome, cpf, sexo, nascimento, cidade, email, celular, autorizacao) {

      document.getElementById('v-nome_completo').innerText    = nome;
      document.getElementById('v-cpf').innerText              = cpf;
      document.getElementById('v-sexo').innerText             = sexo;
      document.getElementById('v-data_de_nascimento').innerText = nascimento;
      document.getElementById('v-cidade').innerText           = cidade;
      document.getElementById('v-e_mail').innerText           = email;
      document.getElementById('v-celular_whatsapp').innerText = celular;
      document.getElementById('v-autorizacao').innerText      = autorizacao;

      document.getElementById('modalVer').style.display = 'block';
    }

    function abrirExcluir(id) {
      document.getElementById('del-id').value = id;
      document.getElementById('modalExcluir').style.display = 'block';
    }

    function fecharModal(id) {
      document.getElementById(id).style.display = 'none';
    }

    function manterAbaLista() {
      const url = new URL(window.location.href);
      url.searchParams.set('aba', 'lista');
      window.location.href = url.toString();
    }


function abrirEditarFromDataset(el) {
  const d = JSON.parse(el.dataset.json);

  document.getElementById('e-id').value                 = d.id;
  document.getElementById('e-cpf').value                = d.cpf;
  document.getElementById('e-nome_completo').value      = d.nome_completo;
  document.getElementById('e-sexo').value               = d.sexo;
  document.getElementById('e-data_de_nascimento').value = d.data_de_nascimento;
  document.getElementById('e-autorizacao').value        = d.autorizacao;
  document.getElementById('e-cep').value                = d.cep;
  document.getElementById('e-endereco').value           = d.endereco;
  document.getElementById('e-bairro').value             = d.bairro;
  document.getElementById('e-cidade').value             = d.cidade;
  document.getElementById('e-estado').value             = d.estado;
  document.getElementById('e-e_mail').value             = d.e_mail;
  document.getElementById('e-celular_whatsapp').value   = d.celular_whatsapp;

  document.getElementById('modalEditar').style.display = 'block';
}


// ── DEVOLUÇÕES ───────────────────────────────────────────────

function ordenarDev(campo) {
  const params       = new URLSearchParams(window.location.search);
  const ordenarAtual = params.get('ordenar') || 'd.created_at';
  const ordemAtual   = params.get('ordem')   || 'DESC';
  const novaOrdem    = (ordenarAtual === campo && ordemAtual === 'ASC') ? 'DESC' : 'ASC';
  params.set('ordenar', campo);
  params.set('ordem',   novaOrdem);
  params.set('pagina',  '1');
  window.location.href = 'devolucoes.php?' + params.toString();
}

function abrirVerDev(el) {
  const d = JSON.parse(el.dataset.json);

  const motivos = {
    tamanho_errado: 'Tamanho errado',
    defeito: 'Defeito no produto',
    arrependimento: 'Arrependimento',
    cor_modelo: 'Cor/modelo diferente',
    outro: 'Outro',
  };

  const resolucoes = {
    troca: 'Troca',
    reembolso: 'Reembolso',
    credito_loja: 'Crédito na loja',
    recusada: 'Recusada',
    pendente: 'Pendente',
  };

  const status = {
    aguardando: 'Aguardando',
    em_analise: 'Em análise',
    concluida: 'Concluída',
    recusada: 'Recusada',
  };

  const fmt = (v) => v ? v : '—';
  const fmtDate = (v) => v ? v.split('-').reverse().join('/') : '—';

  document.getElementById('verDevBody').innerHTML = `
    <p><strong>Cliente:</strong> ${fmt(d.nome_completo)}</p>
    <p><strong>CPF:</strong> ${fmt(d.cliente_cpf)}</p>
    <p><strong>Data solicitação:</strong> ${fmtDate(d.data_solicitacao)}</p>
    <p><strong>Produto:</strong> ${fmt(d.produto)} ${d.tamanho ? '('+d.tamanho+')' : ''}</p>
    <p><strong>Motivo:</strong> ${motivos[d.motivo] || d.motivo}</p>
    <p><strong>Descrição:</strong> ${fmt(d.descricao)}</p>
    <p><strong>Resolução:</strong> ${resolucoes[d.resolucao] || d.resolucao}</p>
    <p><strong>Status:</strong> ${status[d.status] || d.status}</p>
    <p><strong>Data resolução:</strong> ${fmtDate(d.data_resolucao)}</p>
    <p><strong>Observações:</strong> ${fmt(d.observacoes)}</p>
  `;

  document.getElementById('modalVerDev').style.display = 'block';
}

function abrirEditarDev(el) {
  const d = JSON.parse(el.dataset.json);
  document.getElementById('ed-id').value             = d.id;
  document.getElementById('ed-produto').value        = d.produto;
  document.getElementById('ed-tamanho').value        = d.tamanho       || '';
  document.getElementById('ed-motivo').value         = d.motivo;
  document.getElementById('ed-resolucao').value      = d.resolucao;
  document.getElementById('ed-status').value         = d.status;
  document.getElementById('ed-data_resolucao').value = d.data_resolucao || '';
  document.getElementById('ed-observacoes').value    = d.observacoes   || '';
  document.getElementById('modalEditarDev').style.display = 'block';
}

function abrirExcluirDev(id) {
  document.getElementById('del-dev-id').value = id;
  document.getElementById('modalExcluirDev').style.display = 'block';
}


// ── ENCOMENDAS ───────────────────────────────────────────────

function ordenarEnc(campo) {
  const params       = new URLSearchParams(window.location.search);
  const ordenarAtual = params.get('ordenar') || 'e.data_prevista';
  const ordemAtual   = params.get('ordem')   || 'ASC';
  const novaOrdem    = (ordenarAtual === campo && ordemAtual === 'ASC') ? 'DESC' : 'ASC';
  params.set('ordenar', campo);
  params.set('ordem',   novaOrdem);
  params.set('pagina',  '1');
  window.location.href = 'encomendas.php?' + params.toString();
}

function abrirVerEnc(el) {
  const e = JSON.parse(el.dataset.json);

  const status = {
    aguardando:  'Aguardando',
    em_producao: 'Em produção',
    pronto:      'Pronto',
    entregue:    'Entregue',
  };

  const fmt     = (v) => v ? v : '—';
  const fmtDate = (v) => v ? v.split('-').reverse().join('/') : '—';
  const fmtVal  = (v) => v
    ? 'R$ ' + parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 })
    : '—';

  document.getElementById('verEncBody').innerHTML = `
    <p><strong>Cliente:</strong> ${fmt(e.nome_completo)}</p>
    <p><strong>CPF:</strong> ${fmt(e.cliente_cpf)}</p>
    <p><strong>Produto:</strong> ${fmt(e.produto_descricao)}</p>
    <p><strong>Tamanho:</strong> ${fmt(e.tamanho)} &nbsp;
       <strong>Cor:</strong> ${fmt(e.cor)} &nbsp;
       <strong>Qtd:</strong> ${e.quantidade || 1}</p>
    <p><strong>Data do pedido:</strong> ${fmtDate(e.data_pedido)}</p>
    <p><strong>Entrega prevista:</strong> ${fmtDate(e.data_prevista)}</p>
    <p><strong>Entrega real:</strong> ${fmtDate(e.data_entrega_real)}</p>
    <p><strong>Valor sinal:</strong> ${fmtVal(e.valor_sinal)} &nbsp;
       <strong>Valor total:</strong> ${fmtVal(e.valor_total)}</p>
    <p><strong>Status:</strong> ${status[e.status] || e.status}</p>
    <p><strong>Observações:</strong> ${fmt(e.observacoes)}</p>
  `;

  document.getElementById('modalVerEnc').style.display = 'block';
}

function abrirEditarEnc(el) {
  const e = JSON.parse(el.dataset.json);
  document.getElementById('ee-id').value            = e.id;
  document.getElementById('ee-produto').value       = e.produto_descricao;
  document.getElementById('ee-tamanho').value       = e.tamanho            || '';
  document.getElementById('ee-cor').value           = e.cor                || '';
  document.getElementById('ee-quantidade').value    = e.quantidade         || 1;
  document.getElementById('ee-sinal').value         = e.valor_sinal        || '';
  document.getElementById('ee-total').value         = e.valor_total        || '';
  document.getElementById('ee-data_prevista').value = e.data_prevista      || '';
  document.getElementById('ee-data_entrega').value  = e.data_entrega_real  || '';
  document.getElementById('ee-status').value        = e.status;
  document.getElementById('ee-obs').value           = e.observacoes        || '';
  document.getElementById('modalEditarEnc').style.display = 'block';
}

function abrirExcluirEnc(id) {
  document.getElementById('del-enc-id').value = id;
  document.getElementById('modalExcluirEnc').style.display = 'block';
}


// ── MÁSCARA CPF (campos dos novos formulários) ───────────────
document.addEventListener('DOMContentLoaded', function () {
  ['dev-cpf', 'enc-cpf'].forEach(function (idInput) {
    const el = document.getElementById(idInput);
    if (!el) return;
    el.addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').slice(0, 11);
      v = v.replace(/(\d{3})(\d)/,       '$1.$2');
      v = v.replace(/(\d{3})(\d)/,       '$1.$2');
      v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      this.value = v;
    });
  });
});

function copiar(id, btn) {
  const texto = document.getElementById(id).innerText;
  navigator.clipboard.writeText(texto).then(() => {
    const original = btn.innerHTML;
    btn.innerHTML = '✅ Copiado!';
    btn.disabled = true;
    setTimeout(() => {
      btn.innerHTML = original;
      btn.disabled = false;
    }, 2000);
  });
}
