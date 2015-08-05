{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}
{*<p>Test: {$test} </p>*}

<fieldset>
<legend>{ts}Mailjet STATS{/ts}</legend>
{if $mailjet_stats}
  {strip}
  <table class="crm-info-panel">
    <tr>
      <td width="25%">{ts}Delivered{/ts}</td>
      <td width="75%">{$mailjet_stats.DeliveredCount}</td>
    </tr>
    <tr>
      <td>{ts}Opened{/ts}</td>
      <td>{$mailjet_stats.OpenedCount}</td>
    </tr>
    <tr>
      <td>{ts}Clicked{/ts}</td>
      <td>{$mailjet_stats.ClickedCount}</td>
    </tr>
    <tr>
      <td>{ts}Bounced{/ts}</td>
      <td>{$mailjet_stats.BouncedCount}</td>
    </tr>
    <tr>
      <td>{ts}Spam{/ts}</td>
      <td>{$mailjet_stats.SpamComplaintCount}</td>
    </tr>
    <tr>
      <td>{ts}Unsubscribed{/ts}</td>
      <td>{$mailjet_stats.UnsubscribedCount}</td>
    </tr>
    <tr>
      <td>{ts}Blocked{/ts}</td>
      <td>{$mailjet_stats.BlockedCount}</td>
    </tr>
    <tr>
      <td>{ts}Queued{/ts}</td>
      <td>{$mailjet_stats.QueuedCount}</td>
    </tr>
    <tr>
      <td>{ts}Total{/ts}</td>
      <td>{$mailjet_stats.ProcessedCount}</td>
    </tr>
  </table>
  {/strip}
{else}
    <div class="messages status no-popup">
        {ts}<strong>Mailjet STATS is not available.</strong>{/ts}
    </div>
{/if}
</fieldset>
{literal}
 <script>
  cj(function($) {
    //remove stats report from the default CiviCRM report as we are more interested in Mailjet's stats
    $("td").filter(function() {
      var text = $(this).text();
      switch (text){
        case 'Click-throughs':
        case 'Successful Deliveries':
        case 'Tracked Opens':
          $(this).closest("tr").remove();
          break;
        default:
          break;
      }
    });
  });

 </script>
 {/literal}
