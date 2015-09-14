<?php 
class Functionmodel extends CI_Model
{
/**************************************************************************/
/**********************    CATEGORIES    **********************************/
/**************************************************************************/   
    function parceCategories()
    {
        $arrayData = array('key'=>CHINAVASION_APIKEY, 'include_content'=>"0");
        $jsonRequest = json_encode($arrayData);

        $curl = curl_init(CHINAVASION_APIURL_CATEGORY);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonRequest);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $jsonResponse = curl_exec($curl);
        $statusResponse = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($statusResponse != 200)
        {
            $msg = "Error: call to URL CHINAVASION_APIURL_CATEGORY failed with status $statusResponse, response $jsonResponse, curl_error ".curl_error($curl).", curl_errno ".curl_errno($curl);
            curl_close($curl);
            return $msg;
        }
        curl_close($curl);
        $result = json_decode($jsonResponse, true);

        if(isset($result['error']))
        {
            return $result['error_message'].'<br/><br/>'.$jsonRequest;
        }
        else
        {
            $this->db->empty_table('products');
            $this->db->empty_table('categories');

            $i=0;
            foreach ($result['categories'] as $value)
            {
                $data = array(
                    'id'=>$i++,
                    'name' => $value['name'],
                    'url' => $value['url']
                );
                $this->db->insert('categories', $data);
            }
            
            return true;
        }
    }

    function listCategories()
    {
        $newProductsArray = array();
        $query = $this->db->query("SELECT id, name, url, parce_status FROM categories ORDER BY name");
        if($query->num_rows() > 0)
        {
            return $query->result();
        }
    }
/**************************************************************************/
/************************    PRODUCTS    **********************************/
/**************************************************************************/
    function parceCategoryProducts($id, $start = 0)
    {
        $query = $this->db->query("SELECT name FROM categories WHERE id = $id LIMIT 1");
        $categoryName = $query->row(0)->name;

        $this->db->delete('products', array('category_name' => $categoryName));

        $arrayData = array('key'=>CHINAVASION_APIKEY, 'categories'=>array($categoryName), 'pagination'=>array('start'=>$start, 'count'=>50));
        $jsonRequest = json_encode($arrayData);

        $curl = curl_init(CHINAVASION_APIURL_PRODUCTLIST);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonRequest);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $jsonResponse = curl_exec($curl);
        $statusResponse = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($statusResponse != 200)
        {
            $msg = "Error: call to URL CHINAVASION_APIURL_PRODUCTLIST failed with status $statusResponse, response $jsonResponse, curl_error ".curl_error($curl).", curl_errno ".curl_errno($curl);
            curl_close($curl);
            return $msg;
        }
        curl_close($curl);
        $result = json_decode($jsonResponse, true);

        if(isset($result['error']))
        {
            return $result['error_message'].'<br/><br/>'.$jsonRequest;
        }
        else
        {
            foreach ($result['products'] as $value)
            {
                $data[] = array(
                    'model_code' => $value['model_code'],
                    'ean' => $value['ean'],
                    'full_product_name' => $value['full_product_name'],
                    'category_name' => $value['category_name'],
                    'price' => $value['price'],
                    'product_url' => $value['product_url'],
                    'status' => $value['status'],
                    'continuity' => $value['continuity']
                );
                // $this->db->insert('products', $data);
            }

            $this->db->insert_batch('products', $data);
            empty($data);

            if($result['pagination']['total'] > $start)
            {
                $start = $start + 50;
                $this->parceCategoryProducts($id, $start);
            }
            else
            {
                $this->db->update('categories', array('parce_status'=>1), "id=".$id);
                return true;
            }
        }
    }

/**************************************************************************/
/**************************************************************************/
/**************************************************************************/

    function parceNewProductsXml($parcexmlurl)
    {
        $this->db->empty_table('newproducts');

        $feed = file_get_contents($parcexmlurl);
        $xml = new SimpleXmlElement($feed);
        
        $rowsCount = 0;
        foreach ($xml->channel->item as $item)
        {
            $sku = explode(' - ', $item->title);

            $item_dc = $item->children('http://purl.org/dc/elements/1.1/');

            $data = array(
                'link' => $item->link,
                'sku' => end($sku),
                'date' => date('Y-m-d', strtotime($item_dc->date)),
            );

            $this->db->insert('newproducts', $data);
            unset($data);
            $rowsCount += $this->db->affected_rows();
        }

        return $rowsCount;
    }

    function getNewProducts()
    {
        $newProductsArray = array();
        $query = $this->db->query("SELECT link, sku FROM newproducts");
        if ($query->num_rows() > 0)
        {
            foreach ($query->result() as $row)
            {
                $query2 = $this->db->query("SELECT id FROM ourproducts WHERE sku = '{$row->sku}' LIMIT 1");
                if($query2->num_rows() == 0)
                {
                    $newProductsArray[] = array($row->link, $row->sku);
                }
            }
            return $newProductsArray;
        }
        else
        {
            return 'No new products';
        }
    }


    // function downloadXml()    
    // {
    //     $url  = 'https://www.chinavasion.com/sitemap.xml';

    //     $ch = curl_init($url);
    //     curl_setopt($ch, CURLOPT_NOBODY, true);
    //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //     $chdata = curl_exec($ch);
    //     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    //     if ($httpCode == 200)
    //     {
    //         $path =  $_SERVER['DOCUMENT_ROOT'].'/download/chinavasion_'.date('Y-m-d').'.xml';
           
    //         if(file_exists($path)){unlink($path);}
           
    //         if (($fp = fopen($path, "a")) !== false)
    //         { 
    //             curl_setopt($ch, CURLOPT_NOBODY, false);
    //             curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //             curl_setopt($ch, CURLOPT_FILE, $fp);
    //             $chdata = curl_exec($ch);
    //             // fwrite($path, $chdata);
    //             curl_close($ch);
    //             fclose($fp);
    //         }
    //         return TRUE;
    //     }
    //     else
    //     {
    //         curl_close($ch);
    //         return FALSE;
    //     }
    // }

    // function parseXml()
    // {
    //     $url = $_SERVER['DOCUMENT_ROOT'].'/download/chinavasion_'.date('Y-m-d').'.xml';
    //     $xml = simplexml_load_file($url);
        
    //     $this->db->empty_table('sitemap');
        
    //     $rowsCount = $counter = 0;

    //     foreach ($xml->url as $item)
    //     {
    //         $data = array(
    //             'url' => $item->loc,
    //             'datetime' => date('Y-m-d', strtotime($item->lastmod)),
    //         );

    //         $this->db->insert('sitemap', $data);
    //         unset($data);
    //         $rowsCount += $this->db->affected_rows();
    //         $counter++;
    //     }

    //     if ($rowsCount == $counter)
    //     {
    //         return $rowsCount;
    //     }
    //     else 
    //     {
    //         return FALSE;
    //     }
    // }

    // function parseResultsGetDateList()
    // {
    //     $query = $this->db->query('SELECT datetime FROM sitemap GROUP BY datetime ORDER BY datetime DESC');
    //     if ($query)
    //     {
    //         return $query->result();
    //     }
    //     else
    //     {
    //         return FALSE;
    //     }
    // }

    // function parseResultsLincs($date)
    // {
    //     $query = $this->db->query('SELECT url FROM sitemap WHERE datetime ="'.$date.'"');
    //     if ($query)
    //     {
    //         return $query->result();
    //     }
    //     else
    //     {
    //         return FALSE;
    //     }
    // }

    // function deleteXmls()
    // {
    //     $files = glob($_SERVER['DOCUMENT_ROOT'].'/download/*.xml');
    //     foreach($files as $file)
    //     { 
    //         if($file != $_SERVER['DOCUMENT_ROOT'].'/download/index.html' ) 
    //         {
    //             unlink($file);
    //         }
    //     }
    //     return true;
    // }

    function parseImages($imgurl)
    {
        include('php/simple_html_dom.php');
        if (!is_dir(SAVE_IMAGES_PATH)) {return 'PLEASE: setup SAVE_IMAGES_PATH path to desctop in application\config\constants.php';}

        $link = str_replace("http://", "https://", trim($imgurl));
        $lin_arr = array();
        $kk = 0;
        $q = 1;

        $curl = curl_init();
        curl_setopt($curl,CURLOPT_URL,$link);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 GTB5'); // set user agent
        $out = curl_exec($curl); // get content

        // echo curl_error($curl);  echo $out; die();

        // Create DOM from URL or file
        $html = str_get_html($out);

        // get the TITLE of current page
        foreach($html->find('h1[class="fn"]') as $h1t)
        {
            //echo $h1t->innertext;
            $title = trim($h1t->innertext);
            $title = strtolower($title);
            $title = str_replace(' - ', '-', $title);
            $title = str_replace(' ', '-', $title);
            $title = str_replace('.', '', $title);
            $title = str_replace('(', '', $title);
            $title = str_replace(',', '', $title);
            $title = str_replace('/', '', $title);
            $title = str_replace('\\', '', $title);
            $title = str_replace('\'', '', $title);
            $title = str_replace('"', '', $title);
            $title = str_replace('#', '', $title);
            $title = str_replace('%', '', $title);
            $title = str_replace('@', '', $title);
            $title = str_replace('?', '', $title);
            $title = str_replace('$', '', $title);
            $title = str_replace('*', '', $title);
            $title = str_replace('~', '', $title);
            $title = str_replace('!', '', $title);
            $title = str_replace('^', '', $title);
            $title = str_replace('&', '', $title);
            $title = str_replace('=', '', $title);
            $title = str_replace('+', '', $title);
            $title = str_replace(')', '', $title);
            $title = str_replace(':', '-', $title);
            $title = str_replace('\'', '', $title);
            $title = str_replace('–', '-', $title);
            $title = str_replace('_', '-', $title);
            $title = str_replace('"', '', $title);
            $title = str_replace('quot;', '', $title);
            
            if (substr($title, -1) == '-') $title = substr($title, 0, -1 );
        }

        $filename = SAVE_IMAGES_PATH.'\gallery\\'.$title; // !!! personally data
        // если есть файл, то пропускаем повторное создание
        if (file_exists($filename)) 
        {
            curl_close($curl); // clean content
            return 'File: '.$title.' already exists at Your DESKTOP';
        }
        else
        {
            // create folder on the desktop
            mkdir(SAVE_IMAGES_PATH.'\\'.$title);
            
            // find & save MAIN image
            // foreach($html->find('a.highslide') as $element)
            // {
                // $main_img_url = "http:$element->href";
                // $ext = pathinfo($main_img_url); // разбиваем его на составные
                // $extension = $ext['extension']; // получаем его расширение
                // $path = SAVE_IMAGES_PATH.'/'.$title.'.'.$extension; // указываем путь, куда будем сохранять изображения
                // file_put_contents($path, file_get_contents($main_img_url)); // само скачивание картинки и сохранение
            // }
            
            foreach($html->find('#xxyts img') as $element)
            {
                $main_img_url = "http:$element->src";
                $ext = pathinfo($main_img_url); // разбиваем его на составные
                $extension = $ext['extension']; // получаем его расширение
                $path = SAVE_IMAGES_PATH.'/'.$title.'.'.$extension; // указываем путь, куда будем сохранять изображения
                file_put_contents($path, file_get_contents($main_img_url)); // само скачивание картинки и сохранение
            }
            
            // find & save ADDITIONAL images
            $i = 1;
            foreach($html->find('#xys img') as $element2)
            {
                $add_img_url = "http:$element2->src";
                $add_img_url = str_replace('.thumb_70x70.jpg','',$add_img_url);
                $add_img_url = str_replace('.thumb_140x140.jpg','',$add_img_url);
                $add_img_url = str_replace('images/thumbnails/','images/',$add_img_url);

                $ext2 = pathinfo($add_img_url); // разбиваем его на составные
                $extension2 = $ext2['extension']; // получаем его расширение
                $path2 = SAVE_IMAGES_PATH.'/'.$title.'/'.$i.'.'.$extension2; // указываем путь, куда будем сохранять изображения
                file_put_contents($path2, file_get_contents($add_img_url)); // само скачивание картинки и сохранение
                $i++;
            }
            
            $gallery = htmlentities($title);
            curl_close($curl); // clean content
            return $gallery;
        }
    }
}
?>