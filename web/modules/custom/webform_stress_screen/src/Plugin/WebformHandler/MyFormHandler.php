<?php
namespace Drupal\webform_stress_screen\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Url;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "myhandler_form_handler",
 *   label = @Translation("MyHandler form handler"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("Do something extra with form submissions"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class MyFormHandler extends WebformHandlerBase {

 public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $values = $webform_submission->getData();

    $score = 0;
    foreach ($values as $key => $val) {
      if (($val == 2) || ($val == '2')) {
        $score = $score +  3;
      }
      elseif (($val == 1) || ($val == '1')) {
        $score = $score + 2;
      }
      elseif (($val == 0) || ($val == '0')) {
        $score = $score + 1;
      }
    }

    //Redirect to results page, based on aggregrated score.
    if ($score <= 15) {
      $nid= 414; // You're In Good Shape
    }
    elseif ($score >= 16 && $score <= 30) {
      $nid= 413; // You could be doing better
    }
    elseif ($score >= 31 && $score <= 45) {
      $nid= 412; // You're in Trouble
    }

    $message = "MyHandler got form data:"."<pre>".print_r(compact('values','score','nid'),true)."</pre>";
    \Drupal::logger('myformhandler')->info($message);
    //$form_state->setRedirect('entity.node.canonical',['node' => $nid]);
    $dest_url = "/node/".$nid;
    $url = Url::fromUri('internal:' . $dest_url);
    $form_state->setRedirectUrl( $url );

  }
}
?>