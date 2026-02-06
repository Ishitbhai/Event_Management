<?php
    require('header.php');
    require_once('database/db_connect.php');

    // Fetch services
    $services = [];
    $query = "SELECT service_title, service_image, service_description FROM services";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $services[] = $row;
        }
    }

    // Fetch Why Choose Aone Hub values dynamically
    $values = [];
    $values_query = "SELECT why_title, why_description FROM why_aone_hub";
    $values_result = mysqli_query($conn, $values_query);
    if ($values_result) {
        while ($row = mysqli_fetch_assoc($values_result)) {
            $values[] = $row;
        }
    }
?>

<link rel="stylesheet" href="css/services.css">
<style>
/* Original styles unchanged */
.services-hero {
    background: linear-gradient(90deg,#7B63E6 0%, #9F8FFF 100%);
    color: #fff;
    padding: 68px 0 32px 0;
    text-align: center;
    font-family: 'Segoe UI', Arial, sans-serif;
}
.services-hero h1 {
    font-size: 2.7em;
    font-weight: 600;
    letter-spacing: 1.5px;
    margin-bottom: 12px;
}
.services-hero p {
    font-size: 1.23em;
    max-width: 650px;
    margin: 0 auto 20px;
    line-height: 1.4;
}
.services-list-section {
    background: #F7F7FC;
    padding: 34px 0 70px 0;
}
.services-wrapper {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 38px;
    max-width: 1080px;
    margin: 0 auto;
}
.service-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 20px rgba(90,90,180,0.08);
    padding: 32px 28px 32px 28px;
    text-align: center;
    width: 295px;
    transition: box-shadow 0.19s, transform 0.14s;
    position: relative;
    overflow: visible;
}
.service-card:hover {
    box-shadow: 0 5px 28px 0 rgba(65,48,160,0.17);
    transform: translateY(-5px) scale(1.028);
}
.service-icon-circle {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg,#7454C8 60%, #948CF5 100%);
    border-radius: 50%;
    margin: 0 auto 17px auto;
    display: flex;
    align-items: center;
    justify-content: center;
}
.service-icon-circle i, .service-icon-circle img {
    font-size: 2.3em;
    color: #fff;
    max-width: 60px;
    max-height: 60px;
    display: block;
    margin: auto;
}
.service-card h3 {
    font-size: 1.27em;
    margin: 13px 0 9px 0;
    font-weight: 600;
    color: #4C377E;
    letter-spacing: 0.3px;
}
.service-card p {
    color: #616083;
    font-size: 1.061em;
    margin: 0;
}
@media (max-width: 820px) {
    .services-wrapper { flex-direction: column; gap: 24px; align-items: center;}
    .service-card { width: 93vw; max-width: 350px;}
}
.values-section {
    background: #fff;
    padding: 58px 0 36px 0;
    text-align: center;
}
.values-section h2 {
    color: #31306A;
    font-size: 2em;
    margin-bottom: 18px;
    font-weight: 600;
}
.values-grid {
    max-width: 830px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 28px;
}
.value-card {
    background: #FAFAFE;
    border: 1.5px solid #E4E0FF;
    border-radius: 12px;
    padding: 26px 16px 18px 16px;
    font-size: 1.03em;
    color: #57418A;
    min-height: 130px;
}
</style>
<!-- HERO SECTION -->
<div class="services-hero">
    <h1>Our Services</h1>
    <p>
        At Aone Hub, we provide a comprehensive suite of event services to turn your vision into reality—<br>
        from planning, and logistics to real-time support.
    </p>
</div>

<!-- SERVICES LIST SECTION -->
<section class="services-list-section">
    <div class="services-wrapper">
        <?php if (!empty($services)): ?>
            <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <div class="service-icon-circle">
                        <?php
                            // If service_image is a FontAwesome class, use <i>
                            // If it is an image file path, use <img>
                            if (preg_match('/^fa[srlb]? /', $service['service_image'])) {
                                // FontAwesome icon class
                                echo '<i class="' . htmlspecialchars($service['service_image']) . '"></i>';
                            } else {
                                // Assume it's an image path
                                echo '<img src="' . htmlspecialchars($service['service_image']) . '" alt="' . htmlspecialchars($service['service_title']) . ' Icon" />';
                            }
                        ?>
                    </div>
                    <h3><?= htmlspecialchars($service['service_title']) ?></h3>
                    <p><?= htmlspecialchars($service['service_description']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center;width:100%;">No services available at the moment.</p>
        <?php endif; ?>
    </div>
</section>

<!-- VALUES SECTION -->
<section class="values-section">
    <h2>Why Choose Aone Hub?</h2>
    <div class="values-grid">
        <?php if (!empty($values)): ?>
            <?php foreach ($values as $value): ?>
                <div class="value-card">
                    <strong><?= htmlspecialchars($value['why_title']) ?>:</strong>
                    <?= htmlspecialchars($value['why_description']) ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="value-card">No reasons available at the moment.</div>
        <?php endif; ?>
    </div>
</section>

<!-- FontAwesome for icons (if not already included) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<?php
    require('footer.php');
?>
