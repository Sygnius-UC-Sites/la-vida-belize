<?php header('Content-type: text/xml'); ?>

<?= '<?xml version="1.0" encoding="UTF-8" ?>' ?>
<urlset  xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">




<?php foreach(App\Models\Cms::all() as $c):
	if($c->seo_url=="home"){
		$seo='';
	}elseif($c->seo_url=="get-quote"){
		continue;
	}else{
		$seo=$c->seo_url;
	}
	 $date = date("Y-m-d", strtotime($c->updated_at));
?>
    <url>
        <loc><?= url($seo)?></loc>
        <lastmod><?= $date?></lastmod>
        <changefreq>always</changefreq>
        <priority>1.00</priority>
    </url>
<?php endforeach;?>

  

<?php foreach(App\Models\Property::where("status","true")->get() as $c):
	if($c->seo_url=="home"){
		$seo='';
	}else{
		$seo=$c->seo_url;
	}
	 $date = date("Y-m-d", strtotime($c->updated_at));
?>
    <url>
        <loc><?= url($seo)?></loc>
        <lastmod><?= $date?></lastmod>
        <changefreq>always</changefreq>
        <priority>1.00</priority>
    </url>
<?php endforeach;?>


<?php  foreach(App\Models\Location::orderBy("id","desc")->get() as $c):

		$seo=$c->seo_url;

	 $date = date("Y-m-d", strtotime($c->updated_at));
?>
    <url>
        <loc><?= url('properties/location/'.$seo)?></loc>
        <lastmod><?= $date?></lastmod>
        <changefreq>always</changefreq>
        <priority>1.00</priority>
    </url>
<?php endforeach;?>

  
  

<?php foreach(App\Models\Attraction::all() as $c):
	if($c->seo_url=="home"){
		$seo='';
	}else{
		$seo=$c->seo_url;
	}
	 $date = date("Y-m-d", strtotime($c->updated_at));
?>
    <url>
        <loc><?= url('attractions/detail/'.$seo)?></loc>
        <lastmod><?= $date?></lastmod>
        <changefreq>always</changefreq>
        <priority>1.00</priority>
    </url>
<?php endforeach;?>

  


</urlset>

