<?php

class MyPage extends AnewtPage
{
	function MyPage()
	{
		AnewtPage::AnewtPage();
		$this->set('blocks', array('header', 'content', 'footer'));
	}

	function build_header() {
		return ax_p('This is the header');
	}

	function build_footer() {
		return ax_p('This is the footer');
	}
}

$p = new MyPage();
$p->append_to('content', ax_p('This is the content.'));
$p->flush();

?>
