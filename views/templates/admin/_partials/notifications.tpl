{*
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0).
 * It is also available through the world-wide-web at this URL: https://opensource.org/licenses/AFL-3.0
 *}

{if isset($failed)}
  <div class="alert alert-danger d-print-none" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true"><i class="material-icons">close</i></span>
    </button>
    <div class="alert-text">
      <p>{$failed|escape:'htmlall':'UTF-8'}</p>
    </div>
  </div>
{/if}

{if isset($warning)}
  <div class="alert alert-warning d-print-none" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true"><i class="material-icons">close</i></span>
    </button>
    <div class="alert-text">
      <p>{$warning|escape:'htmlall':'UTF-8'}</p>
    </div>
  </div>
{/if}

{if isset($success)}
  <div class="alert alert-success d-print-none" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true"><i class="material-icons">close</i></span>
    </button>
    <div class="alert-text">
      <p>{$success|escape:'htmlall':'UTF-8'}</p>
    </div>
  </div>
{/if}

{if isset($info)}
  <div class="alert alert-info d-print-none" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true"><i class="material-icons">close</i></span>
    </button>
    <div class="alert-text">
      <p>{$info|escape:'htmlall':'UTF-8'}</p>
    </div>
  </div>
{/if}