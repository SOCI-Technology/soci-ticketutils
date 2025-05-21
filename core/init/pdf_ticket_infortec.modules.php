<?php
/* Copyright (C) 2004-2014 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2011 Regis Houssin         <regis.houssin@inodbox.com>
 * Copyright (C) 2007      Franky Van Liedekerke <franky.van.liedekerke@telenet.be>
 * Copyright (C) 2010-2014 Juanjo Menent         <jmenent@2byte.es>
 * Copyright (C) 2015      Marcos García         <marcosgdf@gmail.com>
 * Copyright (C) 2017      Ferran Marcet         <fmarcet@2byte.es>
 * Copyright (C) 2018      Frédéric France       <frederic.france@netlogic.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/supplier_order/doc/pdf_muscadet.modules.php
 *	\ingroup    fournisseur
 *	\brief      File of class to generate suppliers orders from muscadet model
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/ticket/modules_ticket.php';
require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';


/**
 *	Class to generate the supplier orders with the muscadet model
 */
class pdf_ticket_infortec extends ModelePDFTicket
{
	/**
	 * @var DoliDb Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int 	Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * @var int page_largeur
	 */
	public $page_largeur;

	/**
	 * @var int page_hauteur
	 */
	public $page_hauteur;

	/**
	 * @var array format
	 */
	public $format;

	/**
	 * @var int marge_gauche
	 */
	public $marge_gauche;

	/**
	 * @var int marge_droite
	 */
	public $marge_droite;

	/**
	 * @var int marge_haute
	 */
	public $marge_haute;

	/**
	 * @var int marge_basse
	 */
	public $marge_basse;

	/**
	 * Issuer
	 * @var Societe object that emits
	 */
	public $emetteur;

	const BG_IMAGE = 'bg.png';
	const LOGO_IMAGE = 'logo.png';
	const LOGO_HEADER = 'logo-header.jpg';

	/**
	 *	Constructor
	 *
	 *  @param	DoliDB		$db      	Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		// Load translation files required by the page
		$langs->loadLangs(array("main", "bills"));

		$this->db = $db;
		$this->name = "ticket_infortec";
		$this->description = $langs->trans('PdfTicketInfortecDescription');
		$this->update_main_doc_field = 0; // Save the name of generated file as the main doc when generating a doc with this template

		// Page size for A4 format
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = 20;
		$this->marge_droite = 20;
		$this->marge_haute = 40;
		$this->marge_basse = 20;

		$this->option_logo = 1; // Display logo
		$this->option_tva = 1; // Manage the vat option FACTURE_TVAOPTION
		$this->option_modereg = 1; // Display payment mode
		$this->option_condreg = 1; // Display payment terms
		$this->option_multilang = 1; // Available in several languages
		$this->option_escompte = 0; // Displays if there has been a discount
		$this->option_credit_note = 0; // Support credit notes
		$this->option_freetext = 1; // Support add of a personalised text
		$this->option_draft_watermark = 1; // Support add of a watermark on drafts

		// Get source company
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code))
		{
			$this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined
		}

		// Define position of columns
		$this->posxdesc = $this->marge_gauche + 1;
		$this->posxdiscount = 162;
		$this->postotalht = 174;

		if (getDolGlobalInt('PRODUCT_USE_UNITS'))
		{
			$this->posxtva = 95;
			$this->posxup = 114;
			$this->posxqty = 132;
			$this->posxunit = 147;
		}
		else
		{
			$this->posxtva = 106;
			$this->posxup = 122;
			$this->posxqty = 145;
			$this->posxunit = 162;
		}

		if (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
			$this->posxup = $this->posxtva; // posxtva is picture position reference
		}
		$this->posxpicture = $this->posxtva - (empty($conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH) ? 20 : $conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH); // width of images
		if ($this->page_largeur < 210)
		{ // To work with US executive format
			$this->posxpicture -= 20;
			$this->posxtva -= 20;
			$this->posxup -= 20;
			$this->posxqty -= 20;
			$this->posxunit -= 20;
			$this->posxdiscount -= 20;
			$this->postotalht -= 20;
		}

		$this->tva = array();
		$this->tva_array = array();
		$this->localtax1 = array();
		$this->localtax2 = array();
		$this->atleastoneratenotnull = 0;
		$this->atleastonediscount = 0;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build pdf onto disk
	 *
	 *  @param		Ticket	$object				Id of object to generate
	 *  @param		Translate			$outputlangs		Lang output object
	 *  @param		string				$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int					$hidedetails		Do not show line details
	 *  @param		int					$hidedesc			Do not show desc
	 *  @param		int					$hideref			Do not show ref
	 *  @return		int										1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs = '', $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $hookmanager, $mysoc, $nblines;

		if (!is_object($outputlangs))
		{
			$outputlangs = $langs;
		}
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (!empty($conf->global->MAIN_USE_FPDF))
		{
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		// Load translation files required by the page
		$outputlangs->loadLangs(array("main", "orders", "companies", "bills", "dict", "products"));

		$nblines = count($object->lines);

		// Loop on each lines to detect if there is at least one image to show
		$realpatharray = array();
		if (!empty($conf->global->MAIN_GENERATE_SUPPLIER_ORDER_WITH_PICTURE))
		{
			for ($i = 0; $i < $nblines; $i++)
			{
				if (empty($object->lines[$i]->fk_product))
				{
					continue;
				}

				$objphoto = new Product($this->db);
				$objphoto->fetch($object->lines[$i]->fk_product);

				if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO'))
				{
					$pdir = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product') . $object->lines[$i]->fk_product . "/photos/";
					$dir = $conf->product->dir_output . '/' . $pdir;
				}
				else
				{
					$pdir = get_exdir($objphoto->id, 0, 0, 0, $objphoto, 'product');
					$dir = $conf->product->dir_output . '/' . $pdir;
				}
				$realpath = '';
				foreach ($objphoto->liste_photos($dir, 1) as $key => $obj)
				{
					if (!getDolGlobalInt('CAT_HIGH_QUALITY_IMAGES'))
					{		// If CAT_HIGH_QUALITY_IMAGES not defined, we use thumb if defined and then original photo
						if ($obj['photo_vignette'])
						{
							$filename = $obj['photo_vignette'];
						}
						else
						{
							$filename = $obj['photo'];
						}
					}
					else
					{
						$filename = $obj['photo'];
					}
					$realpath = $dir . $filename;
					break;
				}

				if ($realpath)
				{
					$realpatharray[$i] = $realpath;
				}
			}
		}
		if (count($realpatharray) == 0)
		{
			$this->posxpicture = $this->posxtva;
		}

		if (!$conf->ticket->dir_output)
		{
			$this->error = $langs->trans("ErrorConstantNotDefined", "SUPPLIER_OUTPUTDIR");
			return 0;
		}

		$object->fetch_thirdparty();

		$deja_regle = 0;
		$amount_credit_notes_included = 0;
		$amount_deposits_included = 0;
		//$amount_credit_notes_included = $object->getSumCreditNotesUsed();
		//$amount_deposits_included = $object->getSumDepositsUsed();

		// Definition of $dir and $file
		if ($object->specimen)
		{
			$dir = $conf->ticket->dir_output;
			$file = $dir . "/SPECIMEN.pdf";
		}
		else
		{
			$objectref = dol_sanitizeFileName($object->ref);
			$dir = $conf->ticket->dir_output . '/' . $objectref;
			$file = $dir . "/" . $objectref . ".pdf";
		}

		if (!file_exists($dir))
		{
			if (dol_mkdir($dir) < 0)
			{
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		}

		if (!file_exists($dir))
		{
			$this->error = $langs->trans("ErrorCanNotCreateDir", $dir);
			return 0;
		}

		// Add pdfgeneration hook
		if (!is_object($hookmanager))
		{
			include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		global $action;
		$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

		$nblines = count($object->lines);

		$pdf = pdf_getInstance($this->format);
		$default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
		$heightforinfotot = 50; // Height reserved to output the info and total part
		$heightforfreetext = (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5); // Height reserved to output the free text on last page
		$heightforfooter = $this->marge_basse + 8; // Height reserved to output the footer (value include bottom margin)
		if (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS))
		{
			$heightforfooter += 6;
		}
		$pdf->SetAutoPageBreak(1, 0);

		if (class_exists('TCPDF'))
		{
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
		// Set path to the background PDF File
		if (!empty($conf->global->MAIN_ADD_PDF_BACKGROUND))
		{
			$pagecount = $pdf->setSourceFile($conf->mycompany->dir_output . '/' . $conf->global->MAIN_ADD_PDF_BACKGROUND);
			$tplidx = $pdf->importPage(1);
		}

		$pdf->Open();
		$pagenb = 0;
		$pdf->SetDrawColor(128, 128, 128);

		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities("Ticket"));
		$pdf->SetCreator("Dolibarr " . DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));

		$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref) . " " . $outputlangs->transnoentities("Ticket") . " " . $outputlangs->convToOutputCharset($object->thirdparty->name));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION'))
		{
			$pdf->SetCompression(false);
		}

		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

		// Positionne $this->atleastonediscount si on a au moins une remise
		for ($i = 0; $i < $nblines; $i++)
		{
			if ($object->lines[$i]->remise_percent)
			{
				$this->atleastonediscount++;
			}
		}
		if (empty($this->atleastonediscount))
		{
			$delta = ($this->postotalht - $this->posxdiscount);
			$this->posxpicture += $delta;
			$this->posxtva += $delta;
			$this->posxup += $delta;
			$this->posxqty += $delta;
			$this->posxunit += $delta;
			$this->posxdiscount += $delta;
			// post of fields after are not modified, stay at same position
		}

		// New page
		$pdf->AddPage();
		if (!empty($tplidx))
		{
			$pdf->useTemplate($tplidx);
		}
		$pagenb++;

		$object->fetch_thirdparty();
		$object->fetch_project();

		/* $this->page_one($pdf, $object, $outputlangs);
		
		$pdf->AddPage();
		$pagenb++; */

		$this->page_two($pdf, $object, $outputlangs);

		$pdf->AddPage();
		$pagenb++;

		$this->page_three($pdf, $object, $outputlangs);

		$pdf->AddPage();
		$pagenb++;

		//$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs);
		$pdf->SetFont('', '', $default_font_size - 1);
		$pdf->MultiCell(0, 3, ''); // Set interline to 3
		$pdf->SetTextColor(0, 0, 0);

		$tab_top = 90 + $top_shift;
		$tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 42 + $top_shift : 10);

		for ($i = 2; $i <= $pagenb; $i++)
		{
			$pdf->setPage($i);
			$this->_pagehead($pdf, $object, $outputlangs);
		}

		// Pied de page
		$this->_pagefoot($pdf, $object, $outputlangs);
		if (method_exists($pdf, 'AliasNbPages'))
		{
			$pdf->AliasNbPages();
		}

		$pdf->Close();

		$pdf->Output($file, 'F');

		// Add pdfgeneration hook
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		global $action;
		$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook < 0)
		{
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
		}

		dolChmod($file);

		$this->result = array('fullpath' => $file);

		return 1; // No error

	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Show payments table
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Ticket		$object			Object order
	 *	@param	int			$posy			Position y in PDF
	 *	@param	Translate	$outputlangs	Object langs for output
	 *	@return int							<0 if KO, >0 if OK
	 */
	protected function _tableau_versements(&$pdf, $object, $posy, $outputlangs)
	{
		// phpcs:enable
		return 1;
	}

	/**
	 *  Show first page
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Ticket		$object			Object order
	 *	@param	Translate	$outputlangs	Object langs for output
	 */
	protected function page_one(&$pdf, $object, $outputlangs)
	{
		$object->fetch_project();
		$object->fetch_thirdparty();

		$project = $object->project;
		$thirdparty = $object->thirdparty;

		$bg = '<img src="file:/' . DOL_DOCUMENT_ROOT . '/core/modules/ticket/doc/infortec/' . self::BG_IMAGE . '">';
		$bg = '';

		$pdf->setPageOrientation('P', false, -10);
		$pdf->writeHTMLCell($this->page_hauteur, $this->page_largeur, -1, 0, $bg);

		$logo = '<img src="file:/' . DOL_DOCUMENT_ROOT . '/core/modules/ticket/doc/infortec/' . self::LOGO_IMAGE . '">';
		$logo = '';

		$pdf->writeHTMLCell(150, 150, 30, $this->page_largeur / 2, $logo);

		$html = '';

		$html .= '<div style="text-align: center; line-height: 1">';

		$html .= '<h1>';
		$html .= 'INFORME TÉCNICO';
		$html .= '</h1>';

		if ($project->id > 0)
		{
			$html .= '<h2>';
			$html .= $project->title;
			$html .= '</h2>';
		}

		if ($thirdparty->id > 0)
		{
			$html .= '<h2>';
			$html .= $thirdparty->getFullName($outputlangs);
			$html .= '</h2>';
		}

		$html .= '<h3>';
		$html .= date('d') . ' de ' . $outputlangs->trans(date('F')) . ' de ' . date('Y');
		$html .= '</h3>';

		$html .= '</div>';

		$pdf->writeHTMLCell(100, 100, 55, 190, $html);

		$pdf->setPageOrientation('P', true, $this->marge_basse);
	}

	/**
	 *  Show second page
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Ticket		$object			Object order
	 *	@param	Translate	$outputlangs	Object langs for output
	 */
	protected function page_two(&$pdf, $object, $outputlangs)
	{
		$project = $object->project;
		$thirdparty = $object->thirdparty;

		$html = '';

		$html .= '<p style="text-align: justify; line-height: 1.2">';
		$html .= 'Este documento es confidencial, contiene detalles técnicos respecto a la implementación del sistema de seguridad {{solution_name}} de {{thirdparty_name}}, llevado a cabo por el equipo de ingeniería de INFORTEC. Esimportante que el presente documento solo pueda ser accedido por el personal responsable del mantenimiento de la plataforma, ya que, en manos equivocadas, puede conducir a comprometer la seguridad digital de la infraestructura.';
		$html .= '</p>';

		$html = str_replace('{{solution_name}}', $project->title, $html);
		$html = str_replace('{{thirdparty_name}}', $thirdparty->getFullName($outputlangs), $html);

		$pdf->writeHTML($html);
	}

	/**
	 *  Show second page
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Ticket		$object			Object order
	 *	@param	Translate	$outputlangs	Object langs for output
	 */
	protected function page_three(&$pdf, $object, $outputlangs)
	{
		$thirdparty = $object->thirdparty;
		$project = $object->project;
		$month = $outputlangs->transnoentitiesnoconv(date('F'));

		$html = '';

		$html .= '<h3 style="text-align: center">';
		$html .= $thirdparty->getFullName($outputlangs);
		$html .= '</h3>';

		$html .= '<p style="text-align: justify; line-height: 1.2">';
		$html .= 'El presente informe de aseguramiento fue realizado durante el mes de {{month}}; el cual se realiza con el fin de identificar las oportunidades de mejora que permite "{{project_title}}” para cerrar las brechas de seguridad y generar un plan de acción, en pro del mejoramiento de las posturas de ciberseguridad en la entidad ante los posibles ataques. De forma general el proyecto consistió en la implementación de los ítems abajo descritos, que permiten validar el estado de la seguridad “{{solution_type}}" del cliente y las posibles mejoras dentro de las normativas de protección, para el mejoramiento y cierre de riesgos existentes dentro del proceso.';
		$html .= '</p>';

		$html = str_replace('{{month}}', $month, $html);
		$html = str_replace('{{project_title}}', $project->title, $html);
		// $html = str_replace('{{solution_type}}', $object->solution_type, $html);

		$pdf->writeHTML($html);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Ticket		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	float|int
	 */
	protected function _pagehead(&$pdf, $object, $outputlangs)
	{
		$html = '';

		$html = '<img src="file:/' . DOL_DOCUMENT_ROOT . '/core/modules/ticket/doc/infortec/' . self::LOGO_HEADER . '">';

		$pdf->writeHTMLCell(75, 50, 0, 0, $html);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show footer of page. Need this->emetteur object
	 *
	 *  @param	TCPDF		$pdf     			PDF
	 *  @param	Ticket		$object				Object to show
	 *  @param	Translate	$outputlangs		Object lang for output
	 *  @param	int			$hidefreetext		1=Hide free text
	 *  @return	int								Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		// $showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
		// return pdf_pagefoot($pdf, $outputlangs, 'SUPPLIER_ORDER_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);


	}
}
