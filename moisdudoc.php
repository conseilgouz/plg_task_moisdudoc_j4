<?php

/** Task MoisDuDoc
 * Version			: 1.0.8
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
use Joomla\Component\Fields\Administrator\Model\FieldModel;

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

	protected function moisdudoc(ExecuteTaskEvent $event): int
	{
		$months = array("jan", "f&eacute;v", "mars", "avr", "mai", "juin", "juil", "ao&ucirc;t", "sept", "oct", "nov", "d&eacute;c");
		$days = array("dim", "lun", "mar", "mer", "jeu", "ven", "sam");
		$this->myparams = $event->getArgument('params');
		$params = new Registry();
		$params->orderby_sec = 'front'; // nécessaire pour Articles Model
		$articles     = new ArticlesModel(array('ignore_request' => true));
		$articles->setState('params', $params);
		$categories = $this->myparams->categories;
		$articles->setState('filter.category_id', $categories);
		$items = $articles->getItems();
		foreach ($items as $item) {
			$fields = FieldsHelper::getFields('com_content.article', $item);
			$film_id = null;
			// attention l'ordre des champs est important car il faut d'abord disposer de l'ID du film ID avant d'essayer d'accéder aux Champs Personnalisés tels que Realisation etc
			$fields_sort = [];
			foreach ($fields as $field) {
				$fields_sort[$field->id] = $field;
			}
			ksort($fields_sort);
			$film_id = $fields_sort[13]->value; // récupère l'ID du Film lié à la Séance, qui permettra de récupérer son Titre et ses Champs Personnalisés
			$lieu_id = $fields_sort[14]->value; // récupère l'ID du Lieu lié à la Séance, qui permettra de récupérer son Titre et ses Champs Personnalisés
			$date_heure =  $fields_sort[12]->value; // récupère la Date et Heure du Champ Personnalisé de la Séance, qu'on va reformater pour l'injecter dans le Champ Personnalisé ayant l'ID 29
			$date = new Date($date_heure);
			$date = $date->format('d') . ' ' . $months[(int)$date->format('m') - 1] . ' ' . $date->format('Y') . ' - ' . $days[(int)$date->format('w')];
			$this->update_onefield($item->id,  $fields_sort[29], $date);
			$dateJour = date_create(date("Y-m-d"));
			$dateSeance = date_create($date_heure);
			$diff = date_diff($dateJour, $dateSeance);
			if ($diff->format("%R") == '-') {
				$catid = 11;
			} else {
				$catid = 12;
			}
			if ($catid != $item->catid) { // need update
				$this->update_Category_Article($item, $catid); // si le Champ Personnalisé
			}
			if ($film_id) { // film_id  doit être renseigné
				$an_article = $this->getOneArticle($film_id);
				$this->update_onefield($item->id,  $fields_sort[37], $an_article->title); // injecte le Titre du Film dans le Champ Personnalisé
				$realisateur = $this->getOneField($an_article, 4); // lit la valeur d'un des Champs Personnalisés du Film
				$this->update_onefield($item->id,  $fields_sort[28], $realisateur); // injecte cette valeur dans le Champ Personnalisé voulu de la Séance
				$dureegroupe = $this->getOneField($an_article, 39); // lit la valeur d'un des Champs Personnalisés du Film
				$this->update_onefield($item->id,  $fields_sort[40], $dureegroupe); // injecte cette valeur dans le Champ Personnalisé voulu de la Séance				
			}
			if ($lieu_id) { // lieu de la seance
				$an_article = $this->getOneArticle($lieu_id);
				$this->update_onefield($item->id,  $fields_sort[38], $an_article->title);
				$province = $this->getOneField($an_article, 18); // lit la valeur d'un des Champs Personnalisés du Film
				$this->update_onefield($item->id,  $fields_sort[41], $province); // injecte cette valeur dans le Champ Personnalisé voulu de la Séance	
			}
		}
		return TaskStatus::OK;
	}
	// récupère un article donné
	protected function getOneArticle($article_id)
	{
		$value = "inconnu";
		$article   = new ArticleModel(array('ignore_request' => true));
		if (!$article) { // model error : exit
			return $value;
		}
		$params = new Registry();
		$article->setState('params', $params);
		$item = $article->getItem($article_id);
		return $item;
	}
	// récuperation de la valeur d'un Champ Personnalisé dans un Article
	protected function getOneField($item, $field_id)
	{
		$fields = FieldsHelper::getFields('com_content.article', $item);
		foreach ($fields as $field) {
			if ($field->id == $field_id) {
				$value = $field->value;
			}
		}
		return $value;
	}
	// met à jour la valeur d'un Champ Personnalisé
	// note : if it does not exist, custom field is created
	function update_onefield($article_id, $field, $value)
	{
		$model = new FieldModel(array('ignore_request' => true));
		$model->setFieldValue($field->id, $article_id, $value);
	}
	// met à jour la Catégorie de l'Article
	function update_Category_Article($article, $value)
	{
		$db = Factory::getDbo();
		try {
			$query = $db->getQuery(true);
			$query->update("#__content")
				->set('catid =' . $value)
				->where('id = ' . $article->id);
			$db->setQuery($query);
			$db->execute();
		} catch (Exception $e) {
		}
	}
}
