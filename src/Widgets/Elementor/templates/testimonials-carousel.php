<div <?php echo $this->get_render_attribute_string('carousel'); ?>>
    <div class="mlmmc-testimonials-wrapper">
        <?php foreach ($testimonials as $index => $testimonial) : ?>
            <div class="mlmmc-testimonial-item">
                <div class="mlmmc-testimonial-inner">
                    <?php if (!empty($testimonial['testimonial_image']['url'])) : ?>
                        <div class="mlmmc-testimonial-image">
                            <img src="<?php echo esc_url($testimonial['testimonial_image']['url']); ?>" alt="<?php echo esc_attr($testimonial['testimonial_name']); ?>">
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($testimonial['testimonial_content'])) : ?>
                        <div class="mlmmc-testimonial-content">
                            <?php echo wp_kses_post($testimonial['testimonial_content']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($testimonial['testimonial_rating'])) : ?>
                        <div class="mlmmc-testimonial-stars">
                            <?php for ($i = 1; $i <= 5; $i++) : ?>
                                <?php if ($i <= $testimonial['testimonial_rating']) : ?>
                                    <span class="star-filled">★</span>
                                <?php else : ?>
                                    <span class="star-empty">☆</span>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mlmmc-testimonial-info">
                        <?php if (!empty($testimonial['testimonial_name'])) : ?>
                            <h4 class="mlmmc-testimonial-name"><?php echo esc_html($testimonial['testimonial_name']); ?></h4>
                        <?php endif; ?>
                        
                        <?php if (!empty($testimonial['testimonial_position'])) : ?>
                            <p class="mlmmc-testimonial-position"><?php echo esc_html($testimonial['testimonial_position']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($layout === 'carousel' && $show_arrows) : ?>
        <div class="mlmmc-testimonial-arrows">
            <span class="mlmmc-testimonial-arrow prev">&#10094;</span>
            <span class="mlmmc-testimonial-arrow next">&#10095;</span>
        </div>
    <?php endif; ?>
    
    <?php if ($layout === 'carousel' && $show_dots) : ?>
        <div class="mlmmc-testimonial-dots">
            <?php foreach ($testimonials as $index => $testimonial) : ?>
                <span class="dot<?php echo $index === 0 ? ' active' : ''; ?>" data-index="<?php echo esc_attr($index); ?>"></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
