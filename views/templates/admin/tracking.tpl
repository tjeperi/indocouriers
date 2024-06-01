{*
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0).
 * It is also available through the world-wide-web at this URL: https://opensource.org/licenses/AFL-3.0
 *}

<div class="tab-pane fade show active" id="resiTabContent" role="tabpanel" aria-labelledby="resiTab">
  <div class="card-body">
    {if ! empty($carrier_name) && ! empty($image_url)}
      <div class="row text-center">
        <div class="col-12 form-group">
          <div class="col-12">
            <img src="{$image_url|escape:'htmlall':'UTF-8'}" alt="" class="imgm img-thumbnail">
          </div>
          <label class="form-control-label label-on-top col-12">
            <strong>{$carrier_name|escape:'htmlall':'UTF-8'}</strong> - {$delay|escape:'htmlall':'UTF-8'}
          </label>
        </div>
      </div>
    {/if}
    {if $shipping_status == "DELIVERED"}
      <div class="row text-center">
        <div class="col-12 form-group">
          <label class="form-control-label label-on-top col-12">
            Nomor resi: <strong>{$resi_number|escape:'htmlall':'UTF-8'}</strong>
          </label>
          <label class="form-control-label label-on-top col-12">
            <span class="badge badge-success rounded">Delivered</span>
          </label>
        </div>
      </div>
    {else}
      <form id="update-resi"
        name="update-resi"
        class="form-horizontal"
        action=""
        method="post">
        <div class="row">
          <div class="col-12 form-group">
            <label for="no_resi" class="form-control-label label-on-top col-12">
              {l s='Update nomor resi' mod='indocouriers'}
            </label>
            <div class="col-12">
              <div class="input-group">
                <input type="text" id="no_resi" name="no_resi" class="form-control" value="{$resi_number|escape:'htmlall':'UTF-8'}" />
                <input type="hidden" name="submitupdateresi" value="1">
                <button id="submit-update-resi" type="submit" class="btn btn-primary">
                  {l s='Update Resi' mod='indocouriers'}
                </button>
              </div>
            </div>
          </div>
        </div>
      </form>
    {/if}
  </div>
</div>
<div class="tab-pane fade" id="trackingTabContent" role="tabpanel" aria-labelledby="trackingTab">
  <div class="card-body">
    {if $manifest}
      <div class="row">
        <div class="col-sm-12 text-center">
          {if $shipping_status == "DELIVERED"}
            <p>Status: <span class="badge badge-success rounded">Delivered</span></p>
          {else}
            <p>Status: <span class="badge badge-warning rounded">Shipping</span></p>
          {/if}
        </div>
      </div>
      <table class="table" style="max-height: 250px !important;">
        <tbody>
            {foreach from=$manifest item='manif'}
              <tr>
                <td class="text-right">
                  {$manif.manifest_date|escape:'htmlall':'UTF-8'} {$manif.manifest_time|escape:'htmlall':'UTF-8'}
                </td>
                <td class="text-left">
                    {$manif.manifest_description|escape:'htmlall':'UTF-8'}
                </td>
              </tr>
            {/foreach}
        </tbody>
      </table>
    {else}
      <table class="table">
        <tbody>
          <tr>
            <td class="text-center">Click <strong>Tracking shipping</strong> button to get data</td>
          </tr>
        </tbody>
      </table>
    {/if}

    {if ! empty($carrier_name) && ! empty($resi_number) && $shipping_status != "DELIVERED"}
    <div class="row">
      <div class="col-sm-12">
        <form id="lacak-resi"
        name="lacak-resi"
        class="form-horizontal"
        action=""
        method="post">
          <div class="text-right">
            <input type="hidden" name="submitlacak" value="1">
            <input type="hidden" name="lacak_carrier_name" class="form-control" value="{$carrier_name|escape:'htmlall':'UTF-8'}">
            <input type="hidden" name="lacak_no_resi" class="form-control" value="{$resi_number|escape:'htmlall':'UTF-8'}" />
            <button id="submit-lacak-resi" type="submit" class="btn btn-primary">
              {l s='Tracking shipping' mod='indocouriers'}
            </button>
          </div>
        </form>
      </div>
    </div>
    {/if}

  </div>
</div>