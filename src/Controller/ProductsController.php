<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;
use Cake\Event\Event;
use Cake\Routing\Router;
use Cake\Mailer\Email;
use Cake\Auth\DefaultPasswordHasher;
use Cake\Datasource\ConnectionManager;
//use \CROSCON\CommissionJunction\Client;      



/**
 * Products Controller
 *
 * @property \App\Model\Table\ProductsTable $Products
 *
 * @method \App\Model\Entity\Product[] paginate($object = null, array $settings = [])
 */
class ProductsController extends AppController
{

    
    public function initialize()
    { 
        parent::initialize();
        $this->loadComponent('Cart');    
    }
    
    
        public function beforeFilter(Event $event) {

        parent::beforeFilter($event);



        $this->Auth->allow(['add','booking','slugify' ,'gallerydelete','searchjson','searchdata','searchajax','clear' ,'search','view','index','addtocart','productbycat','promoteproduct','addsellproduct','currencyconverter','savereview']);            

        $this->authcontent();        
    }
    
     private function slugify($str) {   
                // trim the string
                $str = strtolower(trim($str));
                // replace all non valid characters and spaces with an underscore
                $str = preg_replace('/[^a-z0-9-]/', '-', $str);
                $str = preg_replace('/-+/', "-", $str);
        return $str;
     } 
    
    
    /**
     * Index method
     *
     * @return \Cake\Http\Response|void
     */
    public function index()
    {  
        $this->loadModel('Users');
        $this->loadModel('Categories');
        if($this->request->is('post')){
        $uname =  $this->request->data['sellername'];     
        $seller = $this->Users->find('all',['conditions'=>['Users.name LIKE' => '%' . $uname . '%']]); 
        $seller = $seller->first();
        $seller_id = $seller['id'];    
        $this->paginate = [
            'contain' => ['Categories', 'Users'],
            'conditions'=>['Products.user_id'=>$seller_id]
        ];  
        
        }else{

        $this->paginate = [
            'contain' => ['Categories', 'Users','Reviews']
        ];
            
        }
        $products = $this->paginate($this->Products); 
        
        $categories = $this->Categories->find('all',[ 'contain' => ['Products']]); 
        $categories = $categories->all();
        $this->set(compact('products','categories')); 
        $this->set('_serialize', ['products','categories']);  
    }

    
      public function productbycat($slug = NULL) 
    {    
        $this->loadModel('Categories'); 
        $this->loadModel('Users');
        $cat  = $this->Categories->find('all',array('conditions'=>array('Categories.slug'=>$slug)));
        $cat = $cat->first(); 
        if($this->request->is('post')){
        $uname =  $this->request->data['sellername'];     
        $seller = $this->Users->find('all',['conditions'=>['Users.name LIKE' => '%' . $uname . '%']]); 
        $seller = $seller->first();
        $seller_id = $seller['id'];    
        $this->paginate = [ 
            'contain' => ['Categories', 'Users','Reviews'],  
            'conditions'=>['AND'=>['Products.user_id'=>$seller_id,'Products.cat_id'=>$cat['id'],'Products.bonus_disable_admin' => 0]]
        ];  
        
        }else{
             $this->paginate = [
            'contain' => ['Categories', 'Users'],
            'conditions'=>['Products.cat_id'=>$cat['id']]    
        ];
            
        }
  
        $products = $this->paginate($this->Products);   
        
        $categories = $this->Categories->find('all',[ 'contain' => ['Products']]); 
        $categories = $categories->all();

        $this->set(compact('products','cat','categories')); 
        $this->set('_serialize', ['products','categories']); 
    }
    
    
     public function promoteproduct()
    {    
        
    }
    
    
    
    /**
     * View method
     *
     * @param string|null $id Product id.
     * @return \Cake\Http\Response|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($slug = null)
    {
    
        $product = $this->Products->find('all', array('contain'=>array('Users','Galleries','Reviews'=>'Users'),
                'conditions' => ['Products.slug'=>$slug]   
            ));
        $product = $product->first(); 
   
        $this->set('product', $product);
        $this->set('_serialize', ['product']);
    }

     public function searchjson() {
        $term = null;
        if(!empty($this->request->query['term'])) {
            $term = $this->request->query['term'];
            $terms = explode(' ', trim($term));
            $terms = array_diff($terms, array(''));
            $conditions = array(
                // 'Brand.active' => 1,
                'Products.status' => 1
            );
            foreach($terms as $term) {
                $conditions[] = array('Products.name LIKE' => '%' . $term . '%');
            }
            $products = $this->Products->find('all', array(
                'recursive' => -1,
                'contain' => array(
                     'Users'
                ),
                'fields' => array(
                    'Products.id',
                    'Products.name',
                    'Products.image'
                ),
                'conditions' => $conditions,
                'limit' => 20,
            ));
        }
        
         $products = $products->all(); 
          $products = $products->toArray();
        
        echo json_encode($products);
        exit;

    }
    
    public function searchajax() {

      $conn = ConnectionManager::get('default');

       $category_id = $this->request->data['category_type'];
       $lat = $this->request->data['latitude'];
       $long = $this->request->data['longitude'];

       if($this->request->data['category_type']){
      $query="SELECT *, get_distance_in_miles_between_geo_locations('".$lat."','".$long."',`lat`,`long`) as distance FROM products WHERE cat_id = '$category_id' AND lat != '' HAVING distance < 5 AND products.status = '1'";
    }else{
      $query="SELECT *, get_distance_in_miles_between_geo_locations('".$lat."','".$long."',`lat`,`long`) as distance FROM products WHERE  lat != '' HAVING distance < 5 AND products.status = '1'";
    }

    $data = $conn->execute($query);
    $products = $data->fetchAll('assoc');


    
         
      if(!empty($products)){           
             $response['status'] = 'true';
             $response['msg'] = '';   
             $response['data'] = $products;  
        }else{
           $response['status'] = 'false';
           $response['msg'] = 'Data not found.';  
        }
        echo json_encode($response);
         exit;

       

    }

    public function searchdata() {

      $conn = ConnectionManager::get('default');

       $lat = $this->request->getQuery('latitude');
       $long = $this->request->getQuery('longitude');

       if($this->request->getQuery('latitude')){
      $query="SELECT *, get_distance_in_miles_between_geo_locations('".$lat."','".$long."',`lat`,`long`) as distance FROM products WHERE lat != '' HAVING distance < 5 AND products.status = '1'";
    }else{
      $query="SELECT *, get_distance_in_miles_between_geo_locations('".$lat."','".$long."',`lat`,`long`) as distance FROM products WHERE  lat != '' HAVING distance < 5 AND products.status = '1'";
    }

     $categories = $this->Categories->find('all')->all(); 

    $data = $conn->execute($query);
    $products = $data->fetchAll('assoc');
    print_r($product);
    $this->set(compact('products','categories')); 
        $this->set('_serialize', ['products','categories']);

       

    }


    
    public function search() { 


       $conn = ConnectionManager::get('default');
       $lat = $this->request->getQuery('latitude');
       $long = $this->request->getQuery('longitude');

       if($this->request->getQuery('latitude')){
      $query="SELECT *, get_distance_in_miles_between_geo_locations('".$lat."','".$long."',`lat`,`long`) as distance FROM products WHERE lat != '' HAVING distance < 5 AND products.status = '1'";
    }else{
      $query="SELECT *, get_distance_in_miles_between_geo_locations('".$lat."','".$long."',`lat`,`long`) as distance FROM products WHERE lat != '' HAVING distance < 5 AND products.status = '1'";
    }

    $categories = $this->Categories->find('all')->all(); 

   // $color = $this->Products->find(['color'])->group('color'); 
    $color = $this->Products->find('all',['group' => 'Products.color','fields'=> 'Products.color'])->all();
  //heighest and smallest value 

    $queryprice = "SELECT MIN(price) AS minprice,MAX(price) AS maxprice FROM products"; 
    $dataprice = $conn->execute($queryprice);
    $productsprice = $dataprice->fetchAll('assoc');


    $data = $conn->execute($query);
    $products = $data->fetchAll('assoc');
         
        $this->set(compact('products','categories','color','productsprice')); 
        $this->set('_serialize', ['products','categories','color','productsprice']);
        
    }
    
    
    
    /**
     * Add method
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $product = $this->Products->newEntity();
        if ($this->request->is('post')) {
            $product = $this->Products->patchEntity($product, $this->request->getData());
            if ($this->Products->save($product)) {
                $this->Flash->success(__('The product has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The product could not be saved. Please, try again.'));
        }
        $cats = $this->Products->Cats->find('list', ['limit' => 200]);
        $stores = $this->Products->Stores->find('list', ['limit' => 200]);
        $this->set(compact('product', 'cats', 'stores'));
        $this->set('_serialize', ['product']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Product id.
     * @return \Cake\Http\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->loadModel('Galleries');
        if(!empty($this->Auth->user('id'))){
        $product = $this->Products->get($id, [
            'contain' => ['Galleries']
        ]);
      if($this->Auth->user('id') == $product['user_id']){  
        if ($this->request->is(['patch', 'post', 'put'])) {
      

            if ($this->request->data['image'] != 1) {   
                 

                $image = $this->request->data['image'];
          $name = time().$image['name'];
    $tmp_name = $image['tmp_name'];
    $upload_path = WWW_ROOT.'images/products/'.$name;
    move_uploaded_file($tmp_name, $upload_path);
                $this->request->data['image'] = $name;
               }else {
                    unset($this->request->data['image']);
                }
            $this->request->data['user_id'] = $this->Auth->user('id');    
            $product = $this->Products->patchEntity($product, $this->request->getData());
            $saveproduct = $this->Products->save($product);
            if ($saveproduct) {
                
                
                
                if(isset($this->request->data['images'])){
                  if ($this->request->data['images'][0]['name'] != '') {   
                    for($i=0; $i<count($this->request->data['images']);$i++){
                        $fileName = $this->request->data['images'][$i]['name'];
                        $fileName = date('His') . $fileName;
                        $uploadPath = WWW_ROOT.'images/gallery/'.$fileName; 
                        $actual_file[] = $fileName;
                        move_uploaded_file($this->request->data['images'][$i]['tmp_name'], $uploadPath);
                        $post['product_id'] = $saveproduct['id'];
                        $post['image']    = $fileName;
                        $gallery = $this->Galleries->newEntity();                    
                        $gallery = $this->Galleries->patchEntity($gallery,$post);            
                        $this->Galleries->save($gallery);
                    } 
                  }else {
                    unset($this->request->data['images']);
                }    
                }   

                $response['status'] = true;
                $response['msg'] = 'The product has been saved.';
            }else{
                $response['status'] = false; 
                $response['msg'] = 'The product could not be saved. Please, try again.';
            }
            echo json_encode($response);
            exit; 
            

        }
    
     }else{  
          $this->Flash->error(__('You have no access'));  
          return $this->redirect(['controller' => 'stores', 'action' => 'index']);      
      }    
        
        
     }else{  
          $this->Flash->error(__('Please login to the website in order to have access to the request.'));  
          return $this->redirect(['controller' => 'stores', 'action' => 'index']);      
      }   
        $cats = $this->Products->Categories->find('treeList', ['limit' => 200]); 
        $stores = $this->Products->Stores->find('list', ['limit' => 200]);
        $this->set(compact('product', 'cats', 'stores'));
        $this->set('_serialize', ['product']);  
    }  

    /**
     * Delete method
     *
     * @param string|null $id Product id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $product = $this->Products->get($id);
        if ($this->Products->delete($product)) {
            $this->Flash->success(__('The product has been deleted.'));
        } else {
            $this->Flash->error(__('The product could not be deleted. Please, try again.'));
        }

        return $this->redirect(['controller'=>'users','action' => 'myproduct']);
    }
 
    
      public function freesaleproduct(){  
      if($this->Auth->user('id')){ 
          if($this->request->is('post')){
              $saleproduct = $this->request->data['saleproduct'];  
              if(!empty($saleproduct)){
             $this->Products->updateAll(array('free_sale' =>0), array('user_id' =>$this->Auth->user('id')));    
             $saleproduct = $this->request->data['saleproduct'];  
             $product = $this->Products->get($saleproduct);  
             $product['free_sale'] = 1;
             if($this->Products->save($product)){
                 $this->Flash->success(__('Product has been added as BONUS product.')); 
             }else{
                $this->Flash->error(__('The product could not be saved. Please, try again.'));   
             }
             
              }else{
                $this->Flash->error(__('Please select product.'));      
              }  
          }  
        $userproduct  = $this->Products->find('all',array('contain'=>['Users'],'conditions'=>array('Products.user_id'=>$this->Auth->user('id'))));
        $userproduct  = $userproduct->all();   

        }else {
           return $this->redirect(['controller' => 'stores', 'action' => 'index']);    
        }
        
       $bonus = $this->Products->find('all',['contain' => ['Categories','Users'],'conditions'=>['Products.user_id'=>$this->Auth->user('id'),'Products.free_sale'=>1]]) ;
       $bonus = $bonus->first();   

        $this->set(compact('userproduct','bonus'));      
        $this->set('_serialize', ['userproduct','bonus']);     
    } 
    
    
    /************************Add to Cart module********************************/
    
    public function clear() {
    $sesid = $this->request->session()->id();
    $uid = $this->Auth->user('id'); 
      $this->Cart->clear();
      $this->loadModel('Carts');
      $this->Carts->deleteAll(array('Carts.uid'=>$uid,'Carts.sessionid'=>$sesid));
      $this->Flash->error(__('All item(s) removed from your shopping cart'));    
        return $this->redirect('/');
    }
    
      public function addtocart() {
        $this->loadModel('Carts');
        if ($this->request->is('post')) {
             $uid = $this->Auth->user('id');
             $post_seller_id = $this->request->data['seller_id']; 
             if(!empty($uid)){ 
               $uid = $uid;  
             }else{
                 $uid = 0 ;
             }
            $id = $this->request->data['id'];  

            $quantity = isset($this->request->data['quantity']) ? $this->request->data['quantity'] : null;

            $productmodId = isset($this->request->data['mods']) ? $this->request->data['mods'] : null;
            $exits = $this->Carts->find('all',array('conditions'=>array('AND'=>array('Carts.product_id'=>$id,'Carts.sessionid'=>$this->request->session()->id()))));
            $exits = $exits->first();   
            
            $cartfind = $this->Carts->find('all',array(
                'conditions'=>array('Carts.uid'=>$uid,'Carts.sessionid'=>$this->request->session()->id())
                )); 
            $cartfind = $cartfind->first();   

          if($cartfind['seller_id'] != $post_seller_id && !empty($cartfind)){        
               
                    
                    
                    
      echo "<script>if (window.confirm('Are you sure you want to change the seller? While adding item in the cart with the new seller, your previous cart items will be removed.?'))
{
   window.location.href='clear';   
}
else
{
  window.location.href='/crystal';  
}</script>";
      
    }else{ 
                          
                             
                
            
            
            
            if(!empty($exits)){
              $this->Flash->success(__('Product is already added in your cart.'));   
             // $product = true; 
            }else{
            $product = $this->Cart->add($id, $quantity, $productmodId,$uid); 
                if(!empty($product)) { 
                    $this->Flash->success(__($product['name'] . ' is added to your cart successfully.'));
                } else {  
                     $this->Flash->error(__('Unable to add this product to your shopping cart.'));

                } 
            
            }
            
        }   
            
        }  
        
        $this->redirect($this->referer());
    }
    
    public function addsellproduct(){  
      if(!empty($this->Auth->user('id'))) { 
        
       $this->loadModel('Galleries');   
       $product = $this->Products->newEntity();
      // $this->autoRender = false;        

     if ($this->request->is('post')) {
         
               if ($this->request->data['image'] != 1) {   
                 

                $image = $this->request->data['image'];
          $name = time().$image['name'];
    $tmp_name = $image['tmp_name'];
    $upload_path = WWW_ROOT.'images/products/'.$name;
    move_uploaded_file($tmp_name, $upload_path);
                $this->request->data['image'] = $name;
               }else {
                    unset($this->request->data['image']);
                } 
         
            
//                $image = $this->request->data['images'][0];
//                if(!empty($image['name'])){
//          $name = time().$image['name'];
//    $tmp_name = $image['tmp_name'];
//    $upload_path = WWW_ROOT.'images/products/'.$name;
//    move_uploaded_file($tmp_name, $upload_path);
//            $this->request->data['image'] = $name;
//                }else{  
//                    $this->request->data['image'] = '';
//                }
            $this->request->data['user_id'] = $this->Auth->user('id');  
            $this->request->data['slug'] =$this->slugify($this->request->data['name']);
            $product = $this->Products->patchEntity($product, $this->request->getData());
            $saveproduct = $this->Products->save($product);
            if ($saveproduct) {
                
                
                
                if(isset($this->request->data['images'])){
                    for($i=0; $i<count($this->request->data['images']);$i++){
                        $fileName = $this->request->data['images'][$i]['name'];
                        $fileName = date('His') . $fileName;
                        $uploadPath = WWW_ROOT.'images/gallery/'.$fileName; 
                        $actual_file[] = $fileName;
                        move_uploaded_file($this->request->data['images'][$i]['tmp_name'], $uploadPath);
                        $post['product_id'] = $saveproduct['id'];
                        $post['image']    = $fileName;
                        $gallery = $this->Galleries->newEntity();                    
                        $gallery = $this->Galleries->patchEntity($gallery,$post);            
                        $this->Galleries->save($gallery);
                    } 
                }   

                $response['status'] = true;
                $response['msg'] = 'The product has been saved.';
            }else{
                $response['status'] = false; 
                $response['msg'] = 'The product could not be saved. Please, try again.';
            }
            echo json_encode($response);
            exit;   
        }else{
     $cats = $this->Products->Categories->find('treeList', ['limit' => 300]); 
     $this->set(compact('cats','product'));    
     $this->set('_serialize', ['product','cats']);     
    }  
      }else{
          $this->Flash->error(__('Please login to the website in order to have access to the request.'));     
          return $this->redirect(['controller' => 'stores', 'action' => 'index']);      
      }    
    }
    
    public function currencyconverter() { 
     
     if ($this->request->is(array('post','put'))) {       
        $amount = $this->request->data['amount'];
        $from_Currency = $this->request->data['from_currency'];
        $to_Currency = $this->request->data['to_currency'];
     
        $from_Currency = urlencode($from_Currency);
        $to_Currency = urlencode($to_Currency);
        $get = file_get_contents("https://finance.google.com/finance/converter?a=$amount&from=$from_Currency&to=$to_Currency");
        $get = explode("<span class=bld>",$get);
        $get = explode("</span>",$get[1]);
        $converted_currency = preg_replace("/[^0-9\.]/", null, $get[0]);

      
     }  
      echo json_encode($converted_currency);   
        exit;    
    }
    
    
    public function gallerydeleterr(){
        $this->loadModel('Galleries');
        $this->request->allowMethod(['post', 'delete']);
        if($this->request->is('post')){
            
             $id = $this->request->data['id']; 
             $product = $this->Galleries->get($id);
        if ($this->Galleries->delete($product)) {
            $response['status'] = true;
            $response['msg'] = 'The gallery image has been deleted';
        
        } else {
            $response['status'] = false;
            $response['msg'] = 'The product could not be deleted. Please, try again.';
        
        }    
            
        } 
    echo json_encode($response);
    exit;
        
    }
    
    public function savereview(){
       $this->loadModel('Reviews');
        if ($this->request->is('post')) {
        $product_id = $this->request->data['product_id'];
        $punctuality =  $this->request->data['punctuality'];
        $text =  $this->request->data['text'];
        
        $post = array();

        if(!empty($this->Auth->user('id'))){
           $uid =  $this->Auth->user('id');
        }else{
           $uid =  0;   
        }  
        $post['user_id'] = $uid ;
        $post['text'] = $text ;
        $post['rating'] = $punctuality ;
        $post['product_id'] = $product_id ;    
        
        
        $review = $this->Reviews->newEntity();
        $cnt = $this->Reviews->find('all', array('conditions' => array('AND' => array('Reviews.user_id' => $uid, 'Reviews.product_id' => $product_id))));
        $cnt = $cnt->first(); 
        if (empty($cnt)) {
             $review = $this->Reviews->patchEntity($review, $post);
             if ($this->Reviews->save($review)) {
                 
                 
                $datacnt = $this->Reviews->find('all', array('conditions' =>array('Reviews.product_id' => $product_id)));
                $datacnt = $datacnt->all()->toArray();
                $sum = 0;
                foreach($datacnt as $datra ){
                  $sum +=  $datra['rating'];
                }
        
        $count = count($datacnt);
        $avg = (int) $sum / (int)$count ; 
                $av_reiew = $avg?$avg:1;
                $this->Products->updateAll(array('ava_rating' =>$av_reiew),
                 array('Products.id' => $product_id));   
                 
                 
                 
               $this->Flash->success(__('Thanks for review'));
               return $this->redirect('http://' .$_POST['server']);
             }else{
               $this->Flash->error(__('Something Wrong. Try again!'));
               return $this->redirect('http://' .$_POST['server']);     
             }
         
        } else {   
           $this->Flash->success(__('You have been already submitted the review')); 
           return $this->redirect('http://' .$_POST['server']);
        }

  }  

    }
     

    public function booking($id = null) {

        $this->loadModel('Orders');
        $this->loadModel('Reviews');
        $this->loadModel('Galleries');

        $orders = $this->Orders->find('all',array('conditions'=>array('Orders.product_id'=>$id)))->all();       

        $slider = $this->Galleries->find('all',array('conditions'=>array('Galleries.product_id'=>$id)))->all();       

        $this->paginate = [
            'contain' => ['Users'],
            'conditions' => ['Reviews.product_id' => $id ],
            'limit' => 5
        ];
        $reviews = $this->paginate($this->Reviews);
        
       $watercraft = $this->Products->get($id, [
            'contain' => ['Reviews'=> ['Users'],'Users','Orders']
        ]);



       $this->loadModel('Favourites');
       $check = $this->Favourites->find('all',array('conditions'=>array('Favourites.product_id'=>$id,'Favourites.user_id'=>$this->Auth->user('id'))));
        $check = $check->first(); 
          // print_r($check);

        $this->set(compact('watercraft','check','orders','reviews','slider'));
        $this->set('_serialize', ['watercraft']);

    } 

    public function bookingdetail() {

        $bookingdetail = $this->Products->get($this->request->query['product_id'], [
            'contain' => ['Users']
            ]);

        $this->set('bookingdetail', $bookingdetail);
        $this->set('_serialize', ['bookingdetail']);

    } 

    public function rentacraft() {

        $this->loadModel('Reviews');

        $reviews = $this->Reviews->find('all',[ 'contain' => ['Users'],'order' => ['Reviews.id' => 'DESC'],'limit' => 10]); 
        $review = $reviews->all();
        
        $this->set(compact('review'));
        $this->set('_serialize', ['review']);
    } 


    public function addyourcraft() {
       
         $product = $this->Products->newEntity();
        if ($this->request->is('post')) {

            $this->request->data['user_id'] = $this->Auth->user('id');
            $this->request->data['slug'] =$this->slugify($this->request->data['name']);

            $image = $this->request->data['image'];
            $name = time().$image['name'];
            $tmp_name = $image['tmp_name'];
            $upload_path = WWW_ROOT.'images/products/'.$name;
            move_uploaded_file($tmp_name, $upload_path);
            $this->request->data['image'] = $name;

            //latlong

            $lat = $_POST['lat'];
            $lng = $_POST['long'];
            $data = file_get_contents("http://maps.google.com/maps/api/geocode/json?latlng=$lat,$lng&sensor=false");
            $data = json_decode($data);
            $add_array  = $data->results;
            $add_array = $add_array[0];
            $add_array = $add_array->address_components;
            $country = "Not found";
            $state = "Not found"; 
            $city = "Not found";
            foreach ($add_array as $key) {
            if($key->types[0] == 'administrative_area_level_2')
            {
            $city = $key->long_name;
            }
            if($key->types[0] == 'administrative_area_level_1')
            {
            $state = $key->long_name;
            }
            if($key->types[0] == 'country')
            {
            $country = $key->long_name;
            }
            }

            $this->request->data['city'] = $city;

            $product = $this->Products->patchEntity($product, $this->request->getData());
            if ($this->Products->save($product)) {
                $this->Flash->success(__('The product has been saved.'));
                return $this->redirect(['action' => '']);
            }
            $this->Flash->error(__('The product could not be saved. Please, try again.'));
        }


        $categories = $this->Categories->find('all')->all(); 
        $this->set(compact('categories')); 
        $this->set('_serialize', ['categories']);


    } 


    public function edityourcraft($id = null)
    {
        $product = $this->Products->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) { 
            
            	        $post = $this->request->data;

			if($this->request->data['image']['name'] != ''){ 
					
			 	
			 
				$image = $this->request->data['image'];
				$name = time().$image['name'];
				$tmp_name = $image['tmp_name'];
				$upload_path = WWW_ROOT.'images/products/'.$name;
				move_uploaded_file($tmp_name, $upload_path);
				 
				$post['image'] = $name;
			
			}else{
				unset($this->request->data['image']);
				$post = $this->request->data;
            }
            $this->request->data['user_id'] = $this->Auth->user('id');
            $this->request->data['slug'] =$this->slugify($this->request->data['name']);

            $lat = $_POST['lat'];
            $lng = trim($_POST['long']);
            $data = file_get_contents("https://maps.google.com/maps/api/geocode/json?latlng=$lat,$lng&sensor=false&key=AIzaSyBQrWZPh0mrrL54_UKhBI2_y8cnegeex1o");
            $data = json_decode($data);
            $add_array  = $data->results;
            $add_array = $add_array[0];
            $add_array = $add_array->address_components;
            $country = "Not found";
            $state = "Not found"; 
            $city = "Not found";
            foreach ($add_array as $key) {
            if($key->types[0] == 'administrative_area_level_2')
            {
            $city = $key->long_name;
            }
            if($key->types[0] == 'administrative_area_level_1')
            {
            $state = $key->long_name;
            }
            if($key->types[0] == 'country')
            {
            $country = $key->long_name;
            }
            }

            $this->request->data['city'] = $city;

            $product = $this->Products->patchEntity($product, $post );  
            if ($this->Products->save($product)) {
                $this->Flash->success(__('The product has been updated.'));

                return $this->redirect(['controller' => 'Users' , 'action' => 'myproduct']);
            }
            $this->Flash->error(__('The product could not be saved. Please, try again.'));
        }
        $cats = $this->Products->Categories->find('treeList', ['limit' => 200]); 
       // $stores = $this->Products->Stores->find('list', ['limit' => 200]);
        $users = $this->Products->Users->find('list',array('conditions'=>array('Users.role'=>'user'))); 
        $this->set(compact('product', 'cats','users'));  
        $this->set('_serialize', ['product']);
    }

    ////// gallery

    public function gallery($id = null){  
        $gallery = $this->Products->get($id, [
            'contain' => ['Categories', 'Users','Galleries']
        ]);

    
        $this->set('gallery', $gallery);
        $this->set('productid', $id);
        $this->set('_serialize', ['gallery']);
    }

    public function addgallery($productid = null ) 
    {
        $this->loadModel('Galleries'); 
        $gallery = $this->Galleries->newEntity();
        if ($this->request->is('post')) {

                if(isset($this->request->data['image'])){
               
                    for($i=0; $i<count($this->request->data['image']);$i++){
                        $fileName = $this->request->data['image'][$i]['name'];
                        $fileName = date('His') . $fileName;
                        $uploadPath = WWW_ROOT.'images/gallery/'.$fileName; 
                        $actual_file[] = $fileName;
                        move_uploaded_file($this->request->data['image'][$i]['tmp_name'], $uploadPath);
                        $post['product_id'] = $productid;
                        $post['image']    = $fileName;
                        $gallery = $this->Galleries->newEntity();                    
                        $gallery = $this->Galleries->patchEntity($gallery,$post);            
                        $this->Galleries->save($gallery);
                    } 
                     $this->Flash->success(__('The gallery has been saved.'));  
                    return $this->redirect(['action' => 'gallery/'.$productid]);
                }   
   
         
        }
    }

    public function gallerydelete($id = null,$productid = null)
       
    {  
           $this->loadModel('Galleries');
        //$this->request->allowMethod(['post', 'delete']);
        $product = $this->Galleries->get($id);
        if ($this->Galleries->delete($product)) {
            $this->Flash->success(__('The gallery has been deleted.'));
        } else {
            $this->Flash->error(__('The gallery could not be deleted. Please, try again.'));
        }

        return $this->redirect(['controller'=>'products','action' => 'gallery',$productid]);

    }

     
    
} 
