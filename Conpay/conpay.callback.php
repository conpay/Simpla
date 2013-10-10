<?php

if (!isset($order_id)) conpay_get_button();

function conpay_get_button()
{
	chdir ('../../');
	session_start();
	
	require_once('api/Simpla.php');
	$simpla = new Simpla();
	
	$simpla->db->query("SELECT id FROM __payment_methods WHERE module='Conpay' LIMIT 1");
	$conpay = $simpla->payment->get_payment_method($simpla->db->result('id'));
	$conpay->settings = conpay_correct_settings(unserialize($conpay->settings));
	
	$user = NULL;
	if ($user_id = $_SESSION['user_id']) $user = $simpla->users->get_user($user_id);
	
	switch($section = $_GET['section']) {
		case 'cart':
			$products = $simpla->cart->get_cart()->purchases;
			break;
		case 'products':
			$products = array($simpla->products->get_product($_GET['item']));
			break;
		case 'catalog':
		case 'brands':
			// GET-Параметры
			$category_url = ($section == 'catalog')? $_GET['item'] : '';
			$brand_url    = ($section == 'brands')? $_GET['item'] : (($b = $_GET['brand'])? $b : '');
			
			$filter = array();
			$filter['visible'] = 1;
			
			// Если задан бренд, выберем его из базы
			if (!empty($brand_url))
			{
				$brand = $simpla->brands->get_brand((string) $brand_url);
				if (empty($brand)) break;
				$filter['brand_id'] = $brand->id;
			}
			
			// Выберем текущую категорию
			if (!empty($category_url))
			{
				$category = $simpla->categories->get_category((string) $category_url);
				if (empty($category) || (!$category->visible && empty($_SESSION['admin'])))
				{
					break;
				}
				$filter['category_id'] = $category->children;
			}
			
			// Если задано ключевое слово
			$keyword = $simpla->request->get('keyword');
			if (!empty($keyword))
			{
				$filter['keyword'] = $keyword;
			}
			
			// Сортировка товаров, сохраняем в сесси, чтобы текущая сортировка оставалась для всего сайта
			if($sort = $simpla->request->get('sort', 'string'))
			{
				$_SESSION['sort'] = $sort;		
			}
			if (!empty($_SESSION['sort']))
			{
				$filter['sort'] = $_SESSION['sort'];			
			}
			else
			{
				$filter['sort'] = 'position';			
			}
			
			// Свойства товаров
			if(!empty($category))
			{
				$features = array();
				foreach($simpla->features->get_features
				(
					array
					(
						'category_id' => $category->id,
						'in_filter' => 1
					)
				) as $feature)
				{ 
					$features[$feature->id] = $feature;
					if(($val = $simpla->request->get($feature->id)) != '')
					{
						$filter['features'][$feature->id] = $val;	
					}
				}
			}
			
			// Постраничная навигация
			$items_per_page = $simpla->settings->products_num;		
			// Текущая страница в постраничном выводе
			$current_page = $simpla->request->get('page', 'int');	
			// Если не задана, то равна 1
			$current_page = max(1, $current_page);
			// Вычисляем количество страниц
			$products_count = $simpla->products->count_products($filter);
			
			// Показать все страницы сразу
			if($simpla->request->get('page') == 'all')
			{
				$items_per_page = $products_count;	
			}
			
			$pages_num = ceil($products_count / $items_per_page);
			
			$filter['page'] = $current_page;
			$filter['limit'] = $items_per_page;
			
			$products = array($simpla->products->get_products($filter));
			$products = $products[0];
			
			break;
	}
	
	$conpay->items = array();
	$conpay->variants = array();
	
	foreach ($products as $k => $product)
	{
		$item = new stdClass();
		if ($section == 'cart') $product =& $product->product;
		
		$item->name = $product->name;
		
		$images = $simpla->products->get_images(array('product_id' => $product->id));
		$item->image = ($host = 'http://' . $_SERVER['HTTP_HOST'] . '/') . 'files/products/' . preg_replace('/\./s', '.300x300.', $images[0]->filename);
		
		$item->url = $host . 'products/' . $product->url;
		
		if ('cart' == $section)
		{
			$item->price = $products[$k]->variant->price;
			if ($vn = $products[$k]->variant->name) $item->name .= ' ' . $vn;
			$item->id = $product->id . ':' . $products[$k]->variant->id;
		}
		else
		{
			$variants = array();
			foreach($simpla->variants->get_variants(array('product_id' => $product->id, 'in_stock' => true)) as $v)
			{
				$conpay->variants[$v->id] = array('product_id' => $product->id, 'price' => $v->price);
				$variants[$v->id] = $v;
			}
			
			$v = array_shift($simpla->variants->get_variants(array('product_id' => $product->id, 'in_stock' => true)));
			$item->price = $v->price;
			$item->id = $product->id . ':' . $v->id;
		}
		
		$item->category = '';
		$categories = $simpla->categories->get_categories(array('product_id' => $product->id));
		
		do
		{
			$cat = current($categories)->name;
			if ($next = next($categories)) $cat .= ', ';
			else $item->category = $cat;
		} while($next);
		
		//$item->id = $product->id;
		$item->quantity = ($section == 'cart')? $products[$k]->amount : 1;
		
		$conpay->items[] = $item;
	}
	
	$conpay->custom = conpay_get_custom_vars($user);
	
	echo json_encode($conpay);
	exit();
}

function conpay_correct_settings($settings = array())
{
	$default_settings = array
	(
		'button_container_id' => 'conpay-btn-container',
		'button_class_name' => 'conpay-btn',
		'button_tag_name' => 'A',
		'button_text' => '<span class="conpay-btn-credit"><b></b>Купить в кредит</span> от <b>{monthly}</b> р. в месяц',
	);
	
	foreach($default_settings as $k => $v)
	{
		if (!($sv =& $settings[$k])) $sv = $v;
	}
	
	unset($settings['merchant_id']);
	unset($settings['api_key']);
	
	return $settings;
}

function conpay_get_custom_vars($user = NULL, $order = NULL)
{
	$custom = new stdClass();
	
	if ($user)
	{
		$custom->user_email = $user->email;
		$custom->user_id = $user->id;
	}
	if ($order)
	{
		if ($v = $order->name)	$custom->user_name 	= $v;
		if ($v = $order->email)	$custom->user_email = $v;
	}
	
	return $custom;
}

function conpay_get_purchases($order_id)
{
	$host = 'http://' . $_SERVER['HTTP_HOST'];
	$sql = "
	SELECT	p.id AS id,
			CONCAT('$host/products/', p.url) AS url,
			IF (v.name > '', CONCAT(p.name, ' ', v.name), p.name) AS name,
			c.name AS category,
			CONCAT('$host/files/products/', i.filename) AS image,
			o.amount AS quantity,
			v.price AS price
	FROM __products p
	RIGHT JOIN __purchases o ON o.product_id = p.id
	LEFT JOIN __products_categories pc ON p.id = pc.product_id
	LEFT JOIN __categories c ON pc.category_id = c.id
	LEFT JOIN __images i ON p.id = i.product_id
	LEFT JOIN __variants v ON o.variant_id = v.id
	WHERE i.position = 0 AND o.order_id = $order_id
	";
	
	$simpla = new Simpla();
	$simpla->db->query($sql);
	
	return $simpla->db->results();
}

?>