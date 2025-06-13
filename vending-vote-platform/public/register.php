<div class="mb-4">
  <label for="type" class="block text-sm font-medium text-gray-700">Business Type</label>
  <select id="type" name="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    <option value="vending">Vending</option>
    <option value="restaurant">Restaurant</option>
    <option value="cannabis">Cannabis</option>
    <option value="retail">Retail</option>
    <option value="other">Other</option>
  </select>
</div>

<?php
$businessData = [
    'name' => $_POST['business_name'],
    'email' => $_POST['email'],
    'slug' => create_slug($_POST['business_name']),
    'type' => $_POST['type']
]; 