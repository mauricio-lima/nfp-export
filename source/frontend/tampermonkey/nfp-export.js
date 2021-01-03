// ==UserScript==
// @name         Nota Fiscal Paulista Export
// @namespace    http://tampermonkey.net/
// @version      1.0
// @description  try to take over the world!
// @author       Mauricio Lima
// @match        https://www.nfp.fazenda.sp.gov.br/ConsultaUsuario/ConsultaListaNF2.aspx
// @match        https://satsp.fazenda.sp.gov.br/COMSAT/Public/ConsultaPublica/*
// @grant        none
// ==/UserScript==


(function() {
    'use strict';
  
    const NFPDialog = `<div>
              <div id="NFPExportModal" class="modal fade" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Exportar Cupom Fiscal</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                      <div class="row">
                        <div class="col-12">
                          <div class="form-group">
                                <label>Endpoint</label>
                                <input id="service-endpoint" type="text" class="form-control" value="https://localhost/nfp/api/entry" >
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                      <button type="button" class="btn btn-primary"   data-dismiss="modal">
                         Enviar
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="modal fade" id="loadMe" tabindex="-1" role="dialog" aria-labelledby="loadMeLabel">
                <div class="modal-dialog modal-sm" role="">
                  <div class="modal-content">
                    <div class="modal-body text-center">
                      <div class="loader"></div>
                        <div clas="loader-txt">
                          <p>
                            Enviando dados
                            <br>
                            <br>
                            <small><span id="message">Conectando</span></small>
                          </p>
                        </div>
                      </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                      <button type="button" class="btn btn-primary"   data-dismiss="modal">
                         Fechar
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              </div>`
  
    const NFPDialogSpinner = `
           .loader {
              position      : relative;
              text-align    : center;
              margin        : 15px auto 35px auto;
              z-index       : 9999;
              display       : block;
              width         : 80px;
              height        : 80px;
              border        : 10px solid rgba(0, 0, 200, .3);
              border-radius     : 40%;
              border-top-color  : #000;
              animation         : spin 1s ease-in-out infinite;
              -webkit-animation : spin 1s ease-in-out infinite;
           }
  
           @keyframes spin {
              to {
                transform : rotate(360deg);
              }
           }`
  
    const sleep = (milliseconds) => new Promise( (resolve) => setTimeout(resolve, milliseconds) )
  
  
    async function createTag(type, parentQuery, attributes)
    {
       return new Promise( (resolve, reject) => {
          const parent = document.querySelector(parentQuery)
          if (!parent) {
            reject(new Error('Parent not found for tag creating'))
          }
  
          const tag = document.createElement(type)
          for(let name in attributes) {
            switch (name)
            {
                case 'content':
                    tag.textContent = attributes.content
                    break
  
                default:
                   tag.setAttribute(name, attributes[name])
            }
          }
          if ('onload'  in tag) tag.addEventListener('load',  resolve)
          if ('onerror' in tag) tag.addEventListener('error', reject)
          tag.addEventListener('error', reject )
  
          parent.appendChild(tag)
       })
    }
  
  
    async function installBootstrap()
    {
       const getCSS = (el) => {
          if (!el)
            return {}
  
          const styles  = window.getComputedStyle(el)
          const transcript = {}
          for(let index = 0; index < styles.length; index++) {
             let name = styles.item(index)
             transcript[name] = styles.getPropertyValue(name)
          }
  
          return transcript
       }
  
       let cssPreserve = [
                          '#tituloPagina h2',
                          '#tituloPagina h3',
                          '#menuSuperior',
                          '#contedoPrincipalAnchorDiv',
                          '#dadosDoUsuario div',
                          'table.CFTamanhoFixo tbody',
                          'table.CFTamanhoFixo tbody tr td.alinharCentro p',
                          '#ConteudoPrincipal div input',
                         ]
  
       //await sleep(35000)
  
       cssPreserve = cssPreserve.map( (selector) => { return { selector : selector, cssBefore : getCSS($(selector)[0]) } })
       await createTag('link', 'head',  {
           rel  : 'stylesheet',
           href : 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css'
       })
       cssPreserve = cssPreserve.map( (selector) => {
           selector = {...selector, changed : {}}
           const cssAfter = getCSS($(selector.selector)[0])
           for(let name in selector.cssBefore) {
              if (selector.cssBefore[name] == cssAfter[name]) continue
              if (name.substring(0,7) == '-webkit') continue
  
              selector.changed[name] = selector.cssBefore[name]
           }
           return selector
       })
  
       cssPreserve.map( (selector) => {
          $(selector.selector).css(selector.changed)
       })
  
       $('body').css('font-size',  '10px')
       $('label').css('font-size', '1rem')
  
       await createTag('script', 'head',  {
           src : 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js',
       })
    }
  
  
    async function setupServiceDialog(processor)
    {
       $('body').append(NFPDialog)
  
       await createTag('style', 'head',  {
           content : NFPDialogSpinner
       })
  
       $('#loadMe').find('p').css('font-size', '1rem')
       $('#loadMe')
           .on('shown.bs.modal', () => { $('body').css("padding-right","0") })
           .on('hide.bs.modal',  () => { $('.modal-backdrop').remove() })
  
       $('#NFPExportModal')
          .on('shown.bs.modal', (e) => { $('body').css("padding-right","0") })
          .find('.modal-footer .btn-primary').click(() => NFPSendData(processor))
    }
  
  
    async function installJQuery()
    {
       await createTag('script', 'head', {
           src  : 'https://code.jquery.com/jquery-3.5.1.slim.min.js',
           //load : () => nfpLibrary.setup(),
           //script : '$( document ).ready(function() { alert( "ready!" ) })'
       })
    }
  
  
  
    function GetViewProcessor(processor)
    {
       switch (processor)
       {
           case 'v1':
               return GetV1Processor();
  
           case 'v2':
               return GetV2Processor();
  
           case 'v2Detail':
               return GetV2DetailProcessor()
  
           default:
              return GetEmptyProcessor();
  
       }
    }
  
  
    function GetV1Processor()
    {
        function setupCustomizedView()
        {
          const coupon = $('div.CupomFiscal')
          coupon
            .attr('style','font-size:13px; width:430px; padding:15px;')
            .css ({ 'box-shadow' : '10px 10px 18px 6px rgba(0,0,0,0.31)', 'margin-left' : 'auto', 'margin-right': 'auto' })
  
          $('#ConteudoPrincipal div:has(input + input)')
            .children()
            .addClass   ('btn btn-sm btn-info')
            .removeClass('button')
            .css        ({ 'font-size' : '1rem', 'margin' : '4px', 'box-shadow' : '10px 10px 18px 6px rgba(0,0,0,0.31)' })
            .width      (74)
        }
  
        function setupStandardView()
        {
          const coupon = $('div.CupomFiscal')
          coupon
            .removeAttr('style')
            .css ({ 'box-shadow' : '', 'margin-left' : '', 'margin-right' : '' })
            //coupon.removeClass('alinharCentro')
          $('#ConteudoPrincipal div:has(input + input)')
            .children()
            .removeClass('btn btn-sm btn-info')
            .addClass   ('button')
            .css        ({ 'font-size' : '', 'margin' : '', 'box-shadow' : '' })
            .width      (38)
        }
  
        async function setupButton()
        {
          const backButton = $('[name="ctl00$ConteudoPagina$btnVoltar"]')
          if (backButton.length != 1) return
  
          backButton
           .clone()
           .val('Enviar')
           .attr('type', 'button')
           .attr('id',   'btnEnviar')
           .insertBefore(backButton)
           .click( async () => {
              $('#NFPExportModal').modal({
                    show : true
              })
           })
  
           $('#ConteudoPrincipal div:has(input + input)')
             .insertBefore('#ConteudoPrincipal div.CupomFiscal')
             .css({ 'margin-top' : '20px', 'margin-bottom' : '15px' })
  
           $('#ConteudoPrincipal div.CupomFiscal')
             .css({ 'margin-bottom' : '130px' })
        }
  
        function setupView()
        {
           const view = ($('div.CupomFiscal').length > 0) ? localStorage.getItem('custom-view') : 'standard'
           switch (view)
           {
                        case 'customized':
                            setupCustomizedView()
                            break;
  
                        default:
                            setupStandardView()
                            break;
  
           }
           setupButton()
        }
  
        async function setupViewToggler()
        {
            const menu = $('#menuSuperior\\:submenu\\:24')
            if (menu.length != 1) return
  
            const lastCommand = menu.children().last()
            lastCommand
                .clone()
                .insertAfter(lastCommand)
                .children('a')
                .text('Alternar visualização')
                .attr('href', '#')
                .click( () => {
                    const customView = localStorage.getItem('custom-view')
                    localStorage.setItem('custom-view', customView == 'customized' ? 'standard' : 'customized')
                    setupView()
                })
        }
  
        async function getData()
        {
            function getValue(f)
            {
                let value
  
                try
                {
                    value = (f.constructor == Function) ? f() : f
                    value = (value != '') || (value != null) ? value.trim() : undefined
                }
                catch (e)
                {
                    value = undefined
                }
  
                return value
            }
  
            function getDateCCF(dataCleaned)
            {
                const dateCCF = dataCleaned.find('#dadosCorpoCF table:eq(0) tr:eq(0) td:contains("CCF")').text()
                const matches = /^\s*(\d{2}\/\d{2}\/\d{4})\s+(\d{2}\:\d{2}:\d{2})\s+CCF\:\s+(\d+)/g.exec(dateCCF)
                if (matches.length > 0)
                {
                    return {
                        datetime : matches[1] + (matches[2] ? ' ' + matches[2] : ''),
                        ccf      : matches[3]
                    }
                }
                return {}
            }
  
            function getCOO(dataCleaned)
            {
                const cooObject = dataCleaned.find('#dadosCorpoCF table:eq(0) tr:eq(0) td:contains("COO") strong')
                const coo = getValue(cooObject.text()) || ''
                const matches = /^\s*COO\:\s+(\d+)/g.exec(coo)
                if ( (matches) && (matches.length > 0) )
                {
                    return {
                        coo : matches[1]
                    }
                }
                return {}
            }
  
            function getCustomer(dataCleaned)
            {
                const customerObject = dataCleaned.find('#dadosCorpoCF table:eq(1) tr:eq(0) td:contains("CNPJ/CPF")')
                const customer = getValue(customerObject.text()) || ''
                const matches = /^\s*CNPJ\/CPF\s+.+\:\s+(\d+)/g.exec(customer)
                if ( (matches) && (matches.length > 0) )
                {
                    return {
                        customer : {
                            documents : {
                                cpf_cnpj : matches[1]
                            }
                        }
                    }
                }
                return {}
            }
  
            function getItems(dataCleaned)
            {
                const items = []
                dataCleaned.find('#painelItens table').not(':last').each( function () {
                    const row  = $(this).find('tbody tr td')
                    const item = {
                        code        : $(row[1]).text(),
                        description : $(row[2]).text(),
                        unity_price : parseFloat($(row[5]).text().replace(',','.')),
                    }
  
                    const quantityValue = parseFloat($(row[4]).text().replace(',','.'))
                    const quantity = ( quantityValue == 1 ) ? {} : { quantity : quantityValue }
  
                    const stValue = $(row[6]).text()
                    const st = ( !stValue || !trim(stValue) ) ? {} : { st : stValue }
  
                    const itemPriceValue = parseFloat($(row[7]).text().replace(',','.'))
                    const itemPrice = (Math.round((quantityValue * item.unity_price + 0.000) * 100) == Math.round(itemPriceValue * 100)) ? {} : { total_price : itemPriceValue }
  
                    items.push({
                        ...item,
                        ...quantity,
                        ...st,
                        ...itemPrice
                    })
                })
  
                return items
            }
  
            function getSummary(dataCleaned)
            {
                const getTotalsValue = (value => {
                const matches = /^\s*R\$\s+(\d+,\d{2})/g.exec(value)
                if ( (matches) && (matches.length > 0) )
                {
                    return parseFloat(matches[1].replace(',', '.'))
                }
  
                    return 0
                })
  
                const summary = dataCleaned.find('#painelItens table').last()
                const rows = summary.find('tbody tr td:odd')
  
                return { totals : {
                    discount : getTotalsValue($(rows[0]).text()),
                    addition : getTotalsValue($(rows[1]).text()),
                    total    : getTotalsValue($(rows[2]).text())
                }}
            }
  
            const dataCleaned = $('<div></div>');
            const dataSource  = $('.CupomFiscal').children().clone().not('#dadosRodapeCF').appendTo(dataCleaned)
            dataCleaned.find('*').removeAttr('style').removeAttr('class').removeAttr('align')
  
            const data = {
                    store  : {
                        name      : getValue(dataCleaned.find('#dadosCabecalhoCF tbody tr:eq(1) td').text()),
                        address   : getValue(dataCleaned.find('#dadosCabecalhoCF tbody tr:eq(2) td').text()),
                        documents : {
                            cnpj  : getValue(dataCleaned.find('td:contains("CNPJ:") + td').text()),
                            ie    : getValue(dataCleaned.find('td:contains("IE:")   + td').text()),
                            im    : getValue(dataCleaned.find('td:contains("IM:")   + td').text())
                        }
                    },
                    ...getDateCCF (dataCleaned),
                    ...getCOO     (dataCleaned),
                    ...getCustomer(dataCleaned),
                    items  : getItems(dataCleaned),
                    ...getSummary(dataCleaned),
  
                    source : [ '\\', JSON.stringify(dataCleaned.html()).slice(0,-1), '\\"'].join('')
            }
  
            return data
        }
  
        return {
  
            setupView : async function () {
                setupViewToggler()
                setupView()
            },
  
            getData  : async () => await getData()
  
        }
    }
  
  
    function GetV2Processor()
    {
        async function setupButton()
        {
          const printButton = $('#conteudo_btnImprimir')
          if (printButton.length != 1) return
  
          printButton
           .clone()
           .val('Enviar')
           .attr('type', 'button')
           .attr('name', 'ctl00$conteudo$btnEnviar')
           .attr('id',   'btnEnviar')
           .insertBefore(printButton)
           .click( async () => {
              $('#NFPExportModal').modal({
                    show : true
              })
           })
           .parent()
           .insertBefore('.BoxLineStyle')
  
           $('#NFPExportModal')
           //$('#ConteudoPrincipal div:has(input + input)')
           //  .insertBefore('#ConteudoPrincipal div.CupomFiscal')
        }
  
        async function setupView()
        {
           setupButton()
        }
  
        async function getData()
        {
            function getValue(f)
            {
                let value
  
                try
                {
                    value = (f.constructor == Function) ? f() : f
                    value = (value != '') || (value != null) ? value.trim() : undefined
                }
                catch (e)
                {
                    value = undefined
                }
  
                return value
            }
  
            function getLocation(dataCleaned)
            {
                const location = []
  
                location.push(getValue(dataCleaned.find('#conteudo_lblEnderecoEmintente').text()))
                location.push(getValue(dataCleaned.find('#conteudo_lblBairroEmitente').text()))
                location.push(getValue(dataCleaned.find('#conteudo_lblMunicipioEmitente').text()))
                location.push('CEP ' + getValue(dataCleaned.find('#conteudo_lblCepEmitente').text()))
  
                return location.join(' - ')
            }
  
            function getDate(dataCleaned)
            {
                const dateCCF = dataCleaned.find('#conteudo_lblDataEmissao').text()
                const matches = /^\s*(\d{2}\/\d{2}\/\d{4})\s*\-\s*(\d{2}\:\d{2}:\d{2})/g.exec(dateCCF)
                if ( (matches) && (matches.length > 0) )
                {
                    return {
                        datetime : matches[1] + (matches[2] ? ' ' + matches[2] : ''),
                    }
                }
                return {}
            }
  
            function getCOO(dataCleaned)
            {
                const coo = getValue(dataCleaned.find('#conteudo_lblNumeroCfe').text()) || ''
                return {
                    coo : coo
                }
            }
  
            function getCustomer(dataCleaned)
            {
                const customer = getValue(dataCleaned.find('#conteudo_lblCpfConsumidor').text()) || ''
                return {
                    customer : {
                            documents : {
                                cpf_cnpj : customer
                            }
                    }
                }
            }
  
            function getItems(dataCleaned)
            {
                const items = []
                dataCleaned.find('#tableItens tbody tr').even().each( function () {
                    const row  = $(this).find('td')
                    const item = {
                        code        : $(row[1]).text(),
                        description : $(row[2]).text(),
                        unity_price : parseFloat($($(row[5]).contents()[2]).text().replace(',','.'))
                    }
  
                    const quantityValue = parseFloat($(row[3]).text().replace(',','.'))
                    const quantity = ( quantityValue == 1 ) ? {} : { quantity : quantityValue }
  
                    //const stValue = $(row[6]).text()
                    //const st = ( !stValue || !trim(stValue) ) ? {} : { st : stValue }
  
                    const itemPriceValue = parseFloat($(row[7]).text().replace(',','.'))
                    const itemPrice = (Math.round((quantityValue * item.unity_price + 0.000) * 100) == Math.round(itemPriceValue * 100)) ? {} : { total_price : itemPriceValue }
  
                    items.push({
                        ...item,
                        ...quantity,
                        //...st,
                        ...itemPrice
                    })
                })
  
                return { items : items }
            }
  
            function getSummary(dataCleaned)
            {
                const getTotalsValue = (value => {
                    const matches = /^\s*(\d+,\d{2})/g.exec(value)
                    if ( (matches) && (matches.length > 0) )
                    {
                        return parseFloat(matches[1].replace(',', '.'))
                    }
                    return 0
                })
  
                const summary = dataCleaned.find('#conteudo_lblTotal').text()
                return { totals : {
                    discount : undefined,
                    addition : undefined,
                    total    : getTotalsValue(summary)
                }}
            }
  
            const dataCleaned = $('<div></div>');
            const dataSource  = $('#conteudo_divMovimento').children().clone().not('#dadosRodapeCF').appendTo(dataCleaned)
            dataCleaned.find('*').removeAttr('style').removeAttr('class').removeAttr('align')
  
            const data = {
                    store  : {
                        name      : getValue(dataCleaned.find('#conteudo_lblNomeEmitente').text()),
                        address   : getLocation(dataCleaned),
                        documents : {
                            cnpj  : getValue(dataCleaned.find('#conteudo_lblCnpjEmitente').text()),
                            ie    : getValue(dataCleaned.find('#conteudo_lblIeEmitente'  ).text()),
                            im    : undefined
                        }
                    },
                    ...getDate    (dataCleaned),
                    ...getCOO     (dataCleaned),
                    ...getCustomer(dataCleaned),
                    ...getItems   (dataCleaned),
                    ...getSummary (dataCleaned),
  
                    source : [ '\\', JSON.stringify(dataCleaned.html()).slice(0,-1), '\\"'].join('')
            }
  
            return data
        }
  
        return {
  
            setupView : () => {
                setupButton()
            },
  
            getData  : async () => await getData()
        }
    }
  
  
    function GetV2DetailProcessor()
    {
        function setupView()
        {
  
          alert('Inside Details')
          $('#conteudo_tabProdutoServico').trigger('click')
        }
  
        return {
  
            setupView : () => setupView(),
  
            getData   : async () => await getData()
        }
    }
  
  
    function GetEmptyProcessor()
    {
       return {
  
       }
    }
  
  
    function NFPCoupomView()
    {
      if ( $('.CupomFiscal').length != 0 )
          return GetViewProcessor('v1')
  
      if ( $('#conteudo_divMovimento').length != 0 )
          return GetViewProcessor('v2')
  
      if ( $('form[action="DadosLotesEnviadosDadosCupom.aspx"]').length != 0 )
          return GetViewProcessor('v2Detail')
  
      return false
    }
  
  
    async function NFPSendData(processor)
    {
      try
      {
          $('#loadMe').modal({
              backdrop : 'static',   // remove ability to close modal with click
              keyboard :  false,     // remove option to close with keyboard
              show     :  true       // Display loader!)
          })
          await sleep(450)
  
          $('#message').text('Extraindo dados')
          const data = await processor.getData()
  
          if (!data.customer) {
              $('#message').text('Dados do consumidor não foram extraídos')
              await sleep(400)
          }
          if (!data.totals) {
              $('#message').text('Dados de totalização não extraídos')
              await sleep(400)
           }
  
          $('#message').text('Enviando dados')
  
          const request  = fetch($('#service-endpoint').val(), {
              method : 'POST',
              mode   : 'cors',
              body   : JSON.stringify(data)
          })
          const response = await request
  
          //alert('done : ' + response.status)
          $('#loadMe').modal('hide')
      }
      catch (e)
      {
         $('#loadMe').removeData('bs.modal').modal({
             backdrop :  true,
             keyboard :  true,     // remove option to close with keyboard
             show     :  true      // Display loader!)
          })
          $('#message').text(e.message)
      }
    }
  
  
    (async () => {
      try
      {
        await installJQuery()
  
        const processor = NFPCoupomView()
        if (!processor)
            return
  
        processor.setupView()
  
        await installBootstrap()
        await setupServiceDialog(processor)
      }
      catch (e)
      {
        alert(e.message)
      }
    })()
  })()
  
  