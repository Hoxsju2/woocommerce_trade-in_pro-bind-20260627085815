<?php
class TestTradeIn extends WP_UnitTestCase {
  public function test_condition_options_default_not_empty() {
    $conds = WTIP_Helpers::get_condition_options();
    $this->assertNotEmpty($conds);
  }

  public function test_cpt_registered() {
    $obj = get_post_type_object('wtip_request');
    $this->assertNotNull($obj);
  }
}
