<?php

class ControllerStartupSeoUrl extends Controller
{

    public function __construct($registry)
    {
        parent::__construct($registry);
        $registry->load->model('catalog/category');
    }


    public function index()
    {
        // Add rewrite to url class
        if ($this->config->get('config_seo_url')) {
            $this->url->addRewrite($this);
        }

        // Decode URL
        if (isset($this->request->get['_route_'])) {
            $parts = explode('/', $this->request->get['_route_']);

            // remove any empty arrays from trailing
            if (utf8_strlen(end($parts)) == 0) {
                array_pop($parts);
            }


            foreach ($parts as $part) {
                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "url_alias WHERE language_id = '" . (int)$this->config->get('config_language_id') . "' AND  keyword = '" . $this->db->escape($part) . "'");

                if (!$query->num_rows) {
                    $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "url_alias WHERE keyword = '" . $this->db->escape($part) . "'");
                }

                //prd($query);

                if ($query->num_rows) {
                    $url = explode('=', $query->row['query']);

                    if ($url[0] == 'product_id') {
                        $this->request->get['product_id'] = $url[1];
                    }

                    if ($url[0] == 'category_id') {
                        if (!isset($this->request->get['path'])) {
                            $this->request->get['path'] = $url[1];
                        } else {
                            $this->request->get['path'] .= '_' . $url[1];
                        }
                    }

                    if ($url[0] == 'manufacturer_id') {
                        $this->request->get['manufacturer_id'] = $url[1];
                    }

                    if ($url[0] == 'information_id') {
                        $this->request->get['information_id'] = $url[1];
                    }

                    if ($url[0] == 'infocategory_id') {
                        $this->request->get['infocategory_id'] = $url[1];
                    }
                    //

                    if ($query->row['query']
                        && $url[0] != 'information_id'
                        && $url[0] != 'manufacturer_id'
                        && $url[0] != 'category_id'
                        && $url[0] != 'product_id'
                        && $url[0] != 'infocategory_id') {
                        $this->request->get['route'] = $query->row['query'];
                    }
                } else {
                    $this->request->get['route'] = 'error/not_found';

                    break;
                }
            }

            if (!isset($this->request->get['route'])) {
                if (isset($this->request->get['product_id'])) {
                    $this->request->get['route'] = 'product/product';
                } elseif (isset($this->request->get['path'])) {
                    $this->request->get['route'] = 'product/category';
                } elseif (isset($this->request->get['manufacturer_id'])) {
                    $this->request->get['route'] = 'product/manufacturer/info';
                } elseif (isset($this->request->get['information_id'])) {
                    $this->request->get['route'] = 'information/information';
                } elseif (isset($this->request->get['infocategory_id'])) {
                    $this->request->get['route'] = 'catalog/infocategory';
                }
            }
        }
    }

    public function rewrite($link)
    {

        $url_info = parse_url(str_replace('&amp;', '&', $link));

        $url = '';

        $data = [];

        parse_str($url_info['query'], $data);

        foreach ($data as $key => $value) {
            if (isset($data['route'])) {

                if (($data['route'] == 'product/product' && $key == 'product_id')
                    || (($data['route'] == 'product/manufacturer/info'
                            || $data['route'] == 'product/product') && $key == 'manufacturer_id')
                    || ($data['route'] == 'information/information' && $key == 'information_id')
                    || ($data['route'] == 'information/infocategory' && $key == 'infocategory_id')
                ) {

                    // Adding link to Infocategory at start


                    if (!empty($data['infocategory_id'])) {
                        $sql = "SELECT * FROM " . DB_PREFIX . "url_alias WHERE `query` = '" . $this->db->escape('infocategory_id=' . (int)$data['infocategory_id']) . "'";
                        $query = $this->db->query($sql);
                        if ($query->num_rows && $query->row['keyword']) {
                            $url .= '/' . $query->row['keyword'];
                            if (!empty($data['information_id'])) {
                                $data['route'] = 'information/informaion';
                            }
                            unset($data['infocategory_id']);
                        }
                    }

                    if ($key != 'infocategory_id') {
                        $sql = "SELECT * FROM " . DB_PREFIX . "url_alias WHERE `query` = '" . $this->db->escape($key . '=' . (int)$value) . "'";

                        $query = $this->db->query($sql);

                        if ($query->num_rows && $query->row['keyword']) {
                            $url .= '/' . $query->row['keyword'];
                            unset($data[$key]);
                        }

                    }


                } elseif ($data['route'] == 'common/home') {
                    $url .= '/';
                    unset($data[$key]);
                } elseif ($key == 'path') {
                    $categories = explode('_', $value);
                    $category_id = end($categories);

                    // TODO: Is this used at all?

                    // On specific cases, thiss will throw error:
                    $this->load->model('catalog/category');

                    $url = $this->model_catalog_category->getCategorySeoLink($category_id, false) . $url;


                    unset($data[$key]);
                }
            }
        }

        if ($url) {
            unset($data['route']);

            $query = '';

            if ($data) {
                foreach ($data as $key => $value) {
                    $query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((is_array($value) ? http_build_query($value) : (string)$value));
                }

                if ($query) {
                    $query = '?' . str_replace('&', '&amp;', trim($query, '&'));
                }
            }

            return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '')
                . str_replace('/index.php', '', $url_info['path'])
                . "/" . ltrim($url, "/ ") . $query;
        } else {
            return $link;
        }
    }

}
