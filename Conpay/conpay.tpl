{literal}
<script type="text/javascript" src="http://www.conpay.ru/public/api/btn.1.6.min.js"></script>
<script type="text/javascript">

  var conpay_request;
  if (window.XMLHttpRequest)
  {
    conpay_request = new XMLHttpRequest();
  }
  else
  {
    conpay_request = new ActiveXObject("Microsoft.XMLHTTP");
  }
  
  var conpay_url_parts = document.location.pathname.split('/');
  var conpay_site_section_item = '';
  var conpay_site_section_brand = '';
  var conpay_variants = [];
  var conpay_data = {};
  
  switch (conpay_site_section = conpay_url_parts[1])
  {
    case 'catalog':
      
      if (brand = conpay_url_parts[3]) conpay_site_section_brand = brand;
      
    case 'brands':
    case 'products':
      
      conpay_site_section_item = conpay_url_parts[2];
      
    case 'cart':
      
      location_search = (l = document.location.search)? l + '&' : '?'; 
      conpay_request.open
      (
        "GET",
        "/payment/Conpay/conpay.callback.php" + location_search + "section=" + conpay_site_section + "&item=" + conpay_site_section_item + "&brand=" + conpay_site_section_brand,
        true
      );
      conpay_request.send();
      
      break;
  }
  
  conpay_request.onreadystatechange = function()
  {
    if (conpay_request.readyState == 4 && conpay_request.status == 200)
    {
      conpay_add_button();
      /*
      if (window.addEventListener) {
        window.addEventListener("load", conpay_add_button, false);
      }
      else if (window.attachEvent) {
        window.attachEvent("onload", conpay_add_button);
      }
      */
    }
  }
  
  function conpay_add_button()
  {
    var d = eval('(' + conpay_request.responseText + ')');
    conpay_data = d;
{/literal}
{if $module != 'CartView'}
{literal}
    conpay_variants = d.variants;
    for (i in els = document.getElementsByName('variant'))
    {
      els[i].onclick = function() {
        v = conpay_variants[this.value];
        btn = (conpay_site_section == 'catalog')? 'conpay-link-' + v.product_id : conpay_data.settings.button_container_id;
        window.conpay.updateProduct({'price': v.price, 'id': v.product_id + ':' + this.value}, btn)
      }
    }
{/literal}
{/if}
{literal}
    try
    {
      window.conpay.init
      (
        '/payment/Conpay/conpay-proxy.php',
        {
          'className': d.settings.button_class_name,
          'tagName': d.settings.button_tag_name,
          'text': d.settings.button_text,
        },
        d.custom
      );
{/literal}
{if $module == 'ProductsView'}
{literal}
      for (i in d.items)
      {
        window.conpay.addButton(d.items[i], 'conpay-link-' + d.items[i].id.split(':')[0]);
      }
{/literal}
{else}
      window.conpay.addButton(d.items, d.settings.button_container_id);
{/if}
{literal}
    }
    catch(e) {}
  }
  
</script>
{/literal}