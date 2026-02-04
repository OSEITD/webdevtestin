document.addEventListener('DOMContentLoaded', function() {
	const form = document.getElementById('trackingForm');
	if (!form) return;

	form.addEventListener('submit', async function(e) {
		e.preventDefault();
		const track_number = form.track_number.value.trim();
		const phone_number = form.phone_number.value.trim();
		const nrc = form.nrc.value.trim();

		const payload = { track_number, phone_number, nrc };
		try {
				const res = await fetch('api/secure_track.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(payload)
			});
			const data = await res.json();
			if (data.success) {
                
				alert('Tracking successful!');
                
			} else {
				alert('Error: ' + data.error);
			}
		} catch (err) {
			alert('Network error: ' + err.message);
		}
	});
});
