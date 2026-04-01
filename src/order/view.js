import { store, getElement } from '@wordpress/interactivity';

// Register the store
const { state } = store( 'query-filter', {
	state: {
		isLoading: false,
	},
	actions: {
	*navigateOrder(e) {
		e.preventDefault();
		const { ref } = getElement();

		let name, value;

		// Get the current URL and preserve existing query parameters
		const currentURL = new URL(window.location.href);

		name = ref.name;
		value = ref.value || ref.dataset.value;

		// Handle order parameters
		if (name) {
			if (value !== '') {
				const optionEl = ref.options[ref.selectedIndex];
				const orderby = optionEl?.dataset?.orderby;
				const order = optionEl?.dataset?.order;

				if (orderby) {
					currentURL.searchParams.set(name, orderby);
					const orderParam = name.replace('orderby', 'order');

					if (order) {
						currentURL.searchParams.set(orderParam, order);
					} else {
						currentURL.searchParams.delete(orderParam);
					}
				} else {
					// Fallback: remove params if no mapping found
					currentURL.searchParams.delete(name);
					const orderParam = name.replace('orderby', 'order');
					currentURL.searchParams.delete(orderParam);
				}
			} else {
				// If value is empty, remove the parameter
				currentURL.searchParams.delete(name);
				const orderParam = name.replace('orderby', 'order');
				currentURL.searchParams.delete(orderParam);
			}
		}

		// Navigate to the updated URL
		state.isLoading = true;
		try {
			const { actions } = yield import('@wordpress/interactivity-router');
			yield actions.navigate(currentURL.toString());
		} finally {
			state.isLoading = false;
		}
    }
  }
});
