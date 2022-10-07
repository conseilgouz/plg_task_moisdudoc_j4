<?php
/** Task MoisDuDoc
* Version			: 1.0.5
* Package			: Joomla 4.1
* copyright 		: Copyright (C) 2022 ConseilGouz. All rights reserved.
* license    		: http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
*
*/

defined('_JEXEC') or die;
use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Component\Content\Site\Model\ArticlesModel;
use Joomla\Component\Content\Site\Model\ArticleModel;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class PlgTaskMoisdudoc extends CMSPlugin implements SubscriberInterface
{
		use TaskPluginTrait;


	/**
	 * @var boolean
	 * @since 4.1.0
	 */
	protected $autoloadLanguage = true;
	/**
	 * @var string[]
	 *
	 * @since 4.1.0
	 */
	protected const TASKS_MAP = [
		'moisdudoc' => [
			'langConstPrefix' => 'PLG_TASK_MOISDUDOC',
			'form'            => 'moisdudoc',
			'method'          => 'moisdudoc',
		],
	];
	protected $myparams;

	/**
	 * @inheritDoc
	 *
	 * @return string[]
	 *
	 * @since 4.1.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	protected function moisdudoc(ExecuteTaskEvent $event): int {
	    $full = array("janvier","f&eacute;vrier.","mars","avril","mai","juin","juillet","ao&ucirc;t","septembre","octobre","novembre","d&eacute;cembre");
		$this->myparams = $event->getArgument('params');
		$params = new Registry();
		$params->orderby_sec = 'front'; // necessaire pour articles model
		$articles     = new ArticlesModel(array('ignore_request' => true));
		$articles->setState('params', $params);
		$categories = $this->myparams->categories;
		$articles->setState('filter.category_id', $categories);		
		$items = $articles->getItems();
		foreach ($items as &$item){
			$fields = FieldsHelper::getFields('com_content.article',$item);
			$film_id = null;
			// attention l'odre des champs est important car besoin film id pour realisateur
		    foreach ($fields as $field) {
		        if ($field->id == 13) { // id de l'article du film de la seance
		          $film_id = $field->value; // utile pour retrouver le realisateur
		        }
		        if ($field->id == 12) {// date/heure
		            $date_heure = $field->value;
		        }
		        if ($field->id == 29) {// date field : field 29
		            $date =new Date($date_heure);
		            $date= $date->format('d').' '.$full[(int)$date->format('m') - 1].' '.$date->format('Y');
		            $this->update_onefield($item->id, $field, $date);
		        }
		        if ($field->id == 27) {// quand : field 27
		            $dateJour = date_create(date("Y-m-d"));
		            $dateSeance = date_create($date_heure);
		            $diff = date_diff($dateJour,$dateSeance);
		            if ($diff->format("%R") == '-') {
                        $value = "passé";
						$catid = 11;
		            } else {
		               $value = 'à venir';
					   $catid = 12;
		            }
		            if (($value != $field->value) || ($catid != $item->catid) ){ // need update
		              $this->update_onefield($item->id, $field, $value);
					  $this->update_Category_Article($item, $catid);
		            }
		        }
		        if ($field->id == 28) {// realisation  
		           if ($film_id) { // film_id  doit être renseigné
		               $realisateur = $this->getOneField($film_id,4); 
		               $this->update_onefield($item->id, $field, $realisateur);
		           }
		        }
			}				
		}
		return TaskStatus::OK;		
	}	
	// recuperation d'un custom field dans un article
	protected function getOneField($article_id,$field_id) {
	    $value = "inconnu";
	    $article   = new ArticleModel(array('ignore_request' => true));
        if (!$article) { // model error : exit
	        return $value;    
    	}
    	$params = new Registry();
    	$article->setState('params', $params);
    	$item = $article->getItem($article_id);
    	$fields = FieldsHelper::getFields('com_content.article',$item);
    	foreach ($fields as $field) {
    	    if ($field->id == $field_id) { // id de l'aticle du film de la seance
    	        $value = $field->value;
    	    }
    	}
    	return $value;
	}
	// update one custom field value
	// note : if it does not exist, custom field is created
	function update_onefield($article_id, $field,$value) {
	    $model = BaseDatabaseModel::getInstance('Field', 'FieldsModel', array('ignore_request' => true));
	    $model->setFieldValue($field->id, $article_id, $value);	
	}
	// update Article's category 
	function update_Category_Article($article,$value) {
	    $article->catid = 12;
	    // $model     = new ArticleModel(array('ignore_request' => true));
	    // $model->save($article);	
	    $db = Factory::getDbo();
	    try {
	        $query = $db->getQuery(true);
	        $query->update("#__content")
	        ->set('catid ='.$value)
	        ->where('id = '.$article->id);
	        $db->setQuery($query);
	        $db->execute();
	    }	catch ( Exception $e ) {
	    }
	    
	}
}